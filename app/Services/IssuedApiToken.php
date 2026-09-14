<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ApiToken;

final readonly class IssuedApiToken
{
    public function __construct(
        public ApiToken $token,
        public string $plainTextToken,
    ) {}
}
