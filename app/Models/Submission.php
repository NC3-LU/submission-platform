<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Submission extends Model
{
    use HasFactory;
    use HasUuids;
    protected $fillable = [
        'form_id',
        'user_id',
        'ip_address',
        'status',
        'status_metadata'
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
}
