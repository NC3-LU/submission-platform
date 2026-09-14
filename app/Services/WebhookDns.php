<?php

namespace App\Services;

class WebhookDns
{
    public function addresses(string $host): array
    {
        $records = dns_get_record($host, DNS_A | DNS_AAAA);

        return array_values(array_unique(array_filter(array_map(fn ($record) => $record['ip'] ?? $record['ipv6'] ?? null, $records ?: []))));
    }
}
