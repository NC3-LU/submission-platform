<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WebhookDeliveryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'event_id' => $this->event_id, 'event_type' => $this->event_type,
            'status' => $this->status, 'attempts' => $this->attempts, 'max_attempts' => $this->max_attempts,
            'status_code' => $this->status_code, 'error_code' => $this->error_code,
            'next_attempt_at' => $this->next_attempt_at, 'created_at' => $this->created_at, 'updated_at' => $this->updated_at];
    }
}
