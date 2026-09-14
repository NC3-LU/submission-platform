<?php

namespace Tests\Feature\Api;

use App\Services\WebhookDestination;
use App\Services\WebhookDns;
use App\Services\WebhookTransport;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WebhookDestinationTest extends TestCase
{
    public static function unsafeUrls(): array
    {
        return array_map(fn ($url) => [$url], ['http://receiver.example.com', 'https://127.0.0.1', 'https://2130706433', 'https://0177.0.0.1', 'https://0x7f000001', 'https://[::1]', 'https://user:pass@receiver.example.com', 'https://receiver.example.com:8080', 'https://receiver.example.com?token=secret', 'https://receiver.example.com/#fragment', 'https://receiver.example.com\\@127.0.0.1', "https://receiver.example.com/\r\nInjected", 'https://receiver.example.com/%0d%0a', 'file:///etc/passwd']);
    }

    #[DataProvider('unsafeUrls')]
    public function test_ambiguous_and_unsafe_urls_are_rejected_before_dns(string $url): void
    {
        $this->mock(WebhookDns::class)->shouldNotReceive('addresses');
        $this->expectException(ValidationException::class);
        app(WebhookDestination::class)->resolve($url);
    }

    public static function unsafeAddresses(): array
    {
        return array_map(fn ($ip) => [$ip], ['127.0.0.1', '10.1.2.3', '172.16.0.1', '192.168.0.1', '169.254.169.254', '100.100.100.200', '0.0.0.0', '192.0.2.1', '198.18.0.1', '224.0.0.1', '::1', '::ffff:8.8.8.8', 'fd00::1', 'fe80::1', '64:ff9b::a00:1', '2001:db8::1', '2002:7f00:1::', '3fff::1']);
    }

    #[DataProvider('unsafeAddresses')]
    public function test_all_dns_answers_must_be_public(string $ip): void
    {
        $this->mock(WebhookDns::class)->shouldReceive('addresses')->once()->andReturn(['8.8.8.8', $ip]);
        $this->expectException(ValidationException::class);
        app(WebhookDestination::class)->resolve('https://receiver.example.com/events');
    }

    public function test_transport_pins_validated_address_and_disables_redirects_proxies_and_insecure_tls(): void
    {
        $this->mock(WebhookDns::class)->shouldReceive('addresses')->once()->andReturn(['2606:4700:4700::1111']);
        $target = app(WebhookDestination::class)->resolve('https://receiver.example.com/events');
        $options = app(WebhookTransport::class)->options($target, '{}', ['X-Webhook-Delivery' => 'test']);
        $this->assertSame(['receiver.example.com:443:[2606:4700:4700::1111]'], $options[CURLOPT_RESOLVE]);
        $this->assertFalse($options[CURLOPT_FOLLOWLOCATION]);
        $this->assertSame('', $options[CURLOPT_PROXY]);
        $this->assertTrue($options[CURLOPT_SSL_VERIFYPEER]);
        $this->assertSame(2, $options[CURLOPT_SSL_VERIFYHOST]);
        $this->assertSame(CURLPROTO_HTTPS, $options[CURLOPT_PROTOCOLS]);
        $this->assertSame(10, $options[CURLOPT_TIMEOUT]);
        $this->assertSame(3, $options[CURLOPT_CONNECTTIMEOUT]);
    }
}
