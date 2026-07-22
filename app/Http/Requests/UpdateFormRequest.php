<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('form')) ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|max:255',
            'description' => 'nullable',
            'status' => 'required|in:draft,published,archived',
            'visibility' => 'required|in:public,authenticated,private',
            'available_from' => 'nullable|date',
            'available_until' => 'nullable|date|after_or_equal:available_from',
            'header_image' => 'nullable|image|mimes:jpeg,png,webp|max:4096|dimensions:max_width=6000,max_height=6000',
            'header_image_position' => 'nullable|integer|min:0|max:100',
            'header_theme_color' => 'nullable|string|regex:/^#[0-9a-fA-F]{6}$/D',
            'remove_header_image' => 'nullable|boolean',
        ];
    }
}
