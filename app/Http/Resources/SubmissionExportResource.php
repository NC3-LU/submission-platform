<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubmissionExportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'format' => $this->format, 'status' => $this->expires_at->isPast() ? 'expired' : $this->status,
            'row_count' => $this->row_count, 'bytes' => $this->bytes, 'error_code' => $this->error_code,
            'created_at' => $this->created_at, 'expires_at' => $this->expires_at,
            'download_url' => $this->status === 'completed' && $this->expires_at->isFuture()
                ? route('api.forms.exports.download', ['form' => $this->form_id, 'export' => $this->id]) : null,
        ];
    }
}
