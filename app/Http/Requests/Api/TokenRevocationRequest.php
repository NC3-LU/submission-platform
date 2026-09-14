<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class TokenRevocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token_ids' => ['required_without:all_except_current', Rule::prohibitedIf($this->exists('all_except_current')), 'array', 'min:1', 'max:100'],
            'token_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
            'all_except_current' => ['sometimes', Rule::prohibitedIf($this->exists('token_ids')), 'accepted'],
            'include_current' => ['sometimes', 'boolean', Rule::prohibitedIf($this->exists('all_except_current'))],
        ];
    }
}
