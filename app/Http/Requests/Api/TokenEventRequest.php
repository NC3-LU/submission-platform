<?php

namespace App\Http\Requests\Api;

use App\Models\ApiTokenEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class TokenEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
            'action' => ['sometimes', 'string', Rule::in(ApiTokenEvent::ACTIONS)],
            'token_id' => ['sometimes', 'integer', 'min:1'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', ...($this->filled('from') ? ['after_or_equal:from'] : [])],
        ];
    }
}
