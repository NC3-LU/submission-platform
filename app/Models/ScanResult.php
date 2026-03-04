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

    protected $fillable = [
        'submission_id',
        'submission_value_id',
        'is_malicious',
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
