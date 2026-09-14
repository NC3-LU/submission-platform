<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SubmissionExport extends Model
{
    use HasUuids;

    protected $fillable = ['form_id', 'user_id', 'token_id', 'format', 'status', 'path', 'row_count', 'bytes', 'error_code', 'expires_at'];

    protected $casts = ['expires_at' => 'immutable_datetime', 'row_count' => 'integer', 'bytes' => 'integer'];

    protected $hidden = ['path'];

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function token(): BelongsTo
    {
        return $this->belongsTo(ApiToken::class);
    }

    public function artifactPath(): string
    {
        return "exports/{$this->id}/submissions.{$this->format}";
    }
}
