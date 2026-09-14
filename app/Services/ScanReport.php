<?php

namespace App\Services;

final class ScanReport
{
    public static function redact(array $data, int $depth = 0): array
    {
        if ($depth > 32) {
            return [];
        }
        $safe = [];
        foreach ($data as $key => $value) {
            if (preg_match('/seed|token|secret|authorization|cookie|password|^link$|^url$/i', (string) $key)) {
                continue;
            }
            $safe[$key] = is_array($value) ? self::redact($value, $depth + 1) : $value;
        }

        return $safe;
    }
}
