<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

abstract class ApiTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<int, mixed>
     */
    protected function expirationRules(bool $sometimes = false): array
    {
        $rules = $sometimes ? ['sometimes'] : [];
        $rules = [...$rules, 'nullable', 'bail', 'date', 'after:now'];

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        if (! is_string($this->input('allowed_ips'))) {
            return;
        }

        $restrictions = array_filter(array_unique(array_map(
            'trim',
            explode(',', $this->string('allowed_ips')->toString())
        )));

        $this->merge([
            'allowed_ips' => $restrictions === [] ? null : implode(',', $restrictions),
        ]);
    }
}
