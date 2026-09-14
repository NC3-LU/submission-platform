<?php

namespace App\Http\Resources;

use App\Models\ApiTokenEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ApiTokenEvent */
final class ApiTokenEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'token' => ['id' => $this->target_token_id, 'name' => $this->target_token_name, 'fingerprint' => $this->target_token_fingerprint],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
