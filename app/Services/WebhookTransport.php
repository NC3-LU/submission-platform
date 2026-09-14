<?php

namespace App\Services;

class WebhookTransport
{
    public function send(array $target, string $body, array $headers): array
    {
        $handle = curl_init();
        $limit = new WebhookResponseLimit;
        try {
            curl_setopt_array($handle, $this->options($target, $body, $headers) + [
                CURLOPT_WRITEFUNCTION => fn ($handle, string $chunk): int => $limit->body($chunk),
                CURLOPT_HEADERFUNCTION => fn ($handle, string $line): int => $limit->headers($line),
            ]);
            $ok = curl_exec($handle);

            return ['status_code' => curl_getinfo($handle, CURLINFO_RESPONSE_CODE) ?: null,
                'error_code' => $limit->exceeded ? 'response_too_large' : ($ok === false ? 'network_error' : null)];
        } finally {
            curl_close($handle);
        }
    }

    public function options(array $target, string $body, array $headers): array
    {
        $ip = str_contains($target['ip'], ':') ? '['.$target['ip'].']' : $target['ip'];

        return [
            CURLOPT_URL => $target['url'], CURLOPT_RESOLVE => [$target['host'].':443:'.$ip],
            CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json', 'Expect:', ...array_map(fn ($key, $value) => $key.': '.$value, array_keys($headers), $headers)],
            CURLOPT_FOLLOWLOCATION => false, CURLOPT_MAXREDIRS => 0, CURLOPT_PROXY => '',
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_TIMEOUT => 10, CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_LOW_SPEED_TIME => 5, CURLOPT_LOW_SPEED_LIMIT => 1,
            CURLOPT_FRESH_CONNECT => true, CURLOPT_FORBID_REUSE => true,
        ];
    }
}
