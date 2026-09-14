<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\IpUtils;

final class WebhookDestination
{
    public function __construct(private readonly WebhookDns $dns) {}

    public function resolve(string $url): array
    {
        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? '');
        if (! $parts || strlen($url) > 2048 || preg_match('/[\x00-\x20\x7f\\\\]|%0[ad]/i', $url)
            || ($parts['scheme'] ?? '') !== 'https' || ($parts['port'] ?? 443) !== 443
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
            || ! preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/D', $host)) {
            $this->reject();
        }
        $addresses = $this->dns->addresses($host);
        if (! $addresses || count($addresses) > 32) {
            $this->reject();
        }
        foreach ($addresses as $address) {
            if (! $this->publicAddress($address)) {
                $this->reject();
            }
        }

        return ['url' => 'https://'.$host.($parts['path'] ?? '/'), 'host' => $host, 'ip' => $addresses[0]];
    }

    private function publicAddress(string $ip): bool
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }
        if (str_contains($ip, ':')) {
            return IpUtils::checkIp($ip, '2000::/3') && ! IpUtils::checkIp($ip, ['2001::/23', '2001:db8::/32', '2002::/16', '3fff::/20']);
        }

        return ! IpUtils::checkIp($ip, ['0.0.0.0/8', '100.64.0.0/10', '192.0.0.0/24', '192.0.2.0/24', '192.88.99.0/24', '198.18.0.0/15', '198.51.100.0/24', '203.0.113.0/24', '224.0.0.0/4', '240.0.0.0/4']);
    }

    private function reject(): never
    {
        throw ValidationException::withMessages(['url' => 'Use an HTTPS URL on port 443 with a public DNS destination and no credentials, query or fragment.']);
    }
}
