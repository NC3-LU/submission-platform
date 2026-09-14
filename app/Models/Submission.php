<?php

namespace App\Models;

use App\Services\FileCleanup;
use App\Services\Webhooks;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Submission extends Model
{
    use HasFactory;
    use HasUuids;

    public const EDITABLE_STATUSES = ['draft', 'ongoing'];

    public const REVIEW_STATUSES = ['submitted', 'under_review', 'processing', 'approved', 'rejected', 'completed'];

    public const STATUSES = [...self::EDITABLE_STATUSES, ...self::REVIEW_STATUSES];

    protected static function booted(): void
    {
        static::created(fn (Submission $submission) => app(Webhooks::class)->submissionChanged($submission, true));
        static::updated(fn (Submission $submission) => app(Webhooks::class)->submissionChanged($submission, false));
        static::saving(function (Submission $submission) {
            if (! in_array($submission->status, self::STATUSES, true)) {
                throw ValidationException::withMessages(['status' => 'Invalid submission status.']);
            }
        });
        static::deleting(function (Submission $submission) {
            foreach ($submission->values()->whereHas('field', fn ($q) => $q->where('type', 'file'))->get() as $value) {
                if (is_string($value->value)) {
                    FileCleanup::schedule('private', $submission->ownedFilePathVariants($value->value));
                }
            }
        });
    }

    public function scopeVisibleTo($query, User $user)
    {
        if (! $user->isAdmin()) {
            $query->where(fn ($q) => $q->whereNotIn('status', ['draft', 'ongoing'])->orWhere('user_id', $user->id));
        }

        return $query;
    }

    public function delete()
    {
        return DB::transaction(fn () => parent::delete());
    }

    protected $fillable = [
        'request_key',
        'form_id',
        'user_id',
        'ip_address',
        'status',
        'status_metadata',
    ];

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'id';
    }

    protected $casts = [
        'updated_at' => 'datetime',
        'status_metadata' => 'array',
    ];

    /**
     * Get the form that owns the submission.
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    /**
     * Get the submission values associated with the submission.
     */
    public function values(): HasMany
    {
        return $this->hasMany(SubmissionValues::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all scan results for files in this submission.
     */
    public function scanResults(): HasMany
    {
        return $this->hasMany(ScanResult::class);
    }

    /**
     * Check if any files in this submission are flagged as malicious.
     */
    public function hasMaliciousFiles(): bool
    {
        return $this->scanResults()->where('is_malicious', true)->exists();
    }

    /**
     * Get the count of scanned files for this submission.
     */
    public function getScannedFilesCount(): int
    {
        return $this->scanResults()->count();
    }

    /**
     * Determine whether a private-disk path belongs to this submission.
     * Submission files are flat within their server-controlled UUID directory.
     */
    public function ownsFilePath(string $path): bool
    {
        foreach (["submissions/{$this->id}/", "temp-submissions/{$this->id}/"] as $prefix) {
            if (! str_starts_with($path, $prefix)) {
                continue;
            }

            $filename = substr($path, strlen($prefix));

            return $filename !== ''
                && $filename === basename($filename)
                && ! in_array($filename, ['.', '..'], true)
                && ! str_contains($filename, chr(92))
                && ! str_contains($filename, "\0");
        }

        return false;
    }

    /** @return array<int, string> */
    public function ownedFilePathVariants(string $path): array
    {
        if (! $this->ownsFilePath($path)) {
            return [];
        }

        $filename = basename($path);

        return [
            "temp-submissions/{$this->id}/{$filename}",
            "submissions/{$this->id}/{$filename}",
        ];
    }
}
