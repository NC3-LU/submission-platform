<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class IntegrationEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['actor_user_id', 'actor_token_id', 'form_id', 'resource_id', 'action', 'metadata'];

    protected $casts = ['metadata' => 'array', 'created_at' => 'immutable_datetime'];

    public static function record(string $action, ?int $formId, string|int|null $resourceId, ?int $userId, ?int $tokenId = null, array $metadata = []): self
    {
        return self::create(['action' => $action, 'form_id' => $formId, 'resource_id' => $resourceId,
            'actor_user_id' => $userId, 'actor_token_id' => $tokenId, 'metadata' => $metadata]);
    }
}
