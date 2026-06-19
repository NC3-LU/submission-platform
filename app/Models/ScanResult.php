<?php

// app/Models/ScanResult.php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScanResult extends Model
{
    use HasFactory;
    use HasUuids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_CLEAN = 'clean';

    public const STATUS_MALICIOUS = 'malicious';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'submission_id',
        'submission_value_id',
        'is_malicious',
        'status',
        'scan_results',
        'scanner_used',
        'filename',
    ];

    protected $casts = [
        'is_malicious' => 'boolean',
        'scan_results' => 'array',
    ];

    /**
     * Get the submission value this scan belongs to
     */
    public function submissionValue(): BelongsTo
    {
        return $this->belongsTo(SubmissionValues::class, 'submission_value_id');
    }

    /**
     * Get the submission this scan belongs to
     */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }
}
