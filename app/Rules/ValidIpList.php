<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class ValidIpList implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail("The {$attribute} must be a comma-separated list of IP addresses or CIDR ranges.");

            return;
        }

        foreach (array_map('trim', explode(',', $value)) as $restriction) {
            if (! $this->isValidRestriction($restriction)) {
                $fail("The {$attribute} contains an invalid IP address or CIDR range: {$restriction}.");

                return;
            }
        }
    }

    private function isValidRestriction(string $restriction): bool
    {
        if (! str_contains($restriction, '/')) {
            return filter_var($restriction, FILTER_VALIDATE_IP) !== false;
        }

        $parts = explode('/', $restriction);
        if (count($parts) !== 2 || ! ctype_digit($parts[1])) {
            return false;
        }

        $address = $parts[0];
        $prefix = (int) $parts[1];

        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return $prefix <= 32;
        }

        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            return $prefix <= 128;
        }

        return false;
    }
}
