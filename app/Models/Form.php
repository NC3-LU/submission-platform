<?php

namespace App\Models;

use App\Services\FileCleanup;
use App\Services\Webhooks;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class Form extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::updated(fn (Form $form) => app(Webhooks::class)->formChanged($form));
        static::deleting(function (Form $form) {
            if ($form->header_image && $form->header_image === 'form-headers/'.basename($form->header_image)) {
                FileCleanup::schedule('public', [$form->header_image]);
            }
            $fileValues = SubmissionValues::whereIn(
                'submission_id',
                $form->submissions()->select('id')
            )
                ->whereHas('field', fn ($q) => $q->where('type', 'file'))
                ->with('submission')
                ->get();

            foreach ($fileValues as $fileValue) {
                $path = $fileValue->value;

                if (! is_string($path) || ! $fileValue->submission?->ownsFilePath($path)) {
                    continue;
                }

                FileCleanup::schedule('private', $fileValue->submission->ownedFilePathVariants($path));
            }
        });
    }

    public function delete()
    {
        return DB::transaction(fn () => parent::delete());
    }

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'header_image',
        'header_image_position',
        'header_theme_color',
        'status',
        'visibility',
        'available_from',
        'available_until',
    ];

    protected $casts = [
        'available_from' => 'datetime',
        'available_until' => 'datetime',
        'header_image_position' => 'integer',
    ];

    /**
     * Get the user that owns the form.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the fields associated with the form.
     */
    public function fields(): HasMany
    {
        return $this->hasMany(FormField::class);
    }

    /**
     * Get the submissions associated with the form.
     */
    public function exports(): HasMany
    {
        return $this->hasMany(SubmissionExport::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(FormCategory::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function appointedUsers()
    {
        return $this->belongsToMany(User::class)
            ->withPivot('can_edit')
            ->withTimestamps();
    }

    public function accessLinks(): HasMany
    {
        return $this->hasMany(FormAccessLink::class);
    }

    public function canAccess($user = null): bool
    {
        // Public forms are always accessible
        if ($this->visibility === 'public') {
            return true;
        }

        // Authenticated-only forms require just a logged-in user
        if ($this->visibility === 'authenticated') {
            return $user !== null;
        }

        // Private forms require specific access
        if ($this->visibility === 'private') {
            if ($user === null) {
                return false;
            }

            return $user->isAdmin() ||
                $user->id === $this->user_id ||
                $this->appointedUsers()->where('user_id', $user->id)->exists();
        }

        return false;
    }

    public function ensureStructureEditable(): void
    {
        if ($this->submissions()->exists()) {
            throw ValidationException::withMessages([
                'structure' => 'This form has responses. Duplicate it to change its questions while preserving existing answers.',
            ]);
        }
    }

    public function isWithinAvailabilityWindow(): bool
    {
        $now = now();

        if ($this->available_from && $now->lt($this->available_from)) {
            return false;
        }

        if ($this->available_until && $now->gt($this->available_until)) {
            return false;
        }

        return true;
    }

    public function availabilityState(): string
    {
        $now = now();

        if ($this->available_from && $now->lt($this->available_from)) {
            return 'scheduled';
        }

        if ($this->available_until && $now->gt($this->available_until)) {
            return 'closed';
        }

        return 'open';
    }

    /**
     * Public URL of the header image, or null when unset.
     * Form is not soft-deleted, so destroy() may hard-delete the file safely.
     */
    protected function headerImageUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->header_image
                ? Storage::disk('public')->url($this->header_image)
                : null,
        );
    }
}
