<?php

namespace App\Http\Resources;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FormResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array|Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'header_image_url' => $this->header_image_url,
            'header_theme_color' => $this->header_theme_color,
            'status' => $this->status,
            'visibility' => $this->visibility,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'categories' => FormCategoryResource::collection($this->whenLoaded('categories')),
            'fields' => FormFieldResource::collection($this->whenLoaded('fields')),
            'access_links' => FormAccessLinkResource::collection($this->whenLoaded('accessLinks')),
            'user_id' => $this->user_id,
            'user' => new UserResource($this->whenLoaded('user')),
        ];
    }
}
