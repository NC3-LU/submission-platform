<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WebhookEndpointResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'form_id' => $this->form_id, 'url' => $this->url, 'events' => $this->events,
            'enabled' => $this->enabled, 'created_at' => $this->created_at, 'updated_at' => $this->updated_at];
    }
}
