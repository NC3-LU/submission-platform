<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class WebhookEndpoint extends Model
{
    use HasUuids;

    public const EVENTS = ['submission.created', 'submission.status_changed', 'form.status_changed'];

    protected $fillable = ['user_id', 'form_id', 'url', 'secret', 'events', 'enabled'];

    protected $hidden = ['secret'];

    protected $casts = ['secret' => 'encrypted', 'events' => 'array', 'enabled' => 'boolean'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }
}
