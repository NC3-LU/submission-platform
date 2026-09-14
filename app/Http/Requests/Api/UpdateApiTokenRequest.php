<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Models\ApiToken;
use App\Rules\ValidIpList;
use Illuminate\Validation\Rule;

final class UpdateApiTokenRequest extends ApiTokenRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'abilities' => ['sometimes', 'required', 'array'],
            'abilities.*' => ['string', Rule::in(ApiToken::DELEGABLE_ABILITIES)],
            'allowed_ips' => ['sometimes', 'nullable', 'string', 'max:2048', new ValidIpList],
            'expires_at' => $this->expirationRules(sometimes: true),
        ];
    }
}
