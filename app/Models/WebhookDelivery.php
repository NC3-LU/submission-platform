<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class WebhookDelivery extends Model
{
    use HasUuids;

    protected $fillable = ['webhook_endpoint_id', 'user_id', 'event_id', 'event_type', 'payload', 'status', 'attempts', 'max_attempts', 'status_code', 'error_code', 'next_attempt_at'];

    protected $hidden = ['payload'];

    protected $casts = ['payload' => 'array', 'attempts' => 'integer', 'max_attempts' => 'integer', 'next_attempt_at' => 'immutable_datetime'];

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class, 'webhook_endpoint_id');
    }
}
