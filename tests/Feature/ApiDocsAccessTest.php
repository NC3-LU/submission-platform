<?php

namespace Tests\Feature;

use App\Models\ApiSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * The API docs gate used to consult request()->getHost(). The Host header is
 * client-supplied, so any self-registered user could reach /docs/api simply by
 * sending `Host: test.anything`.
 */
class ApiDocsAccessTest extends TestCase
{
    use RefreshDatabase;

    private function allowDomains(string $domains): void
    {
        ApiSetting::updateOrCreate(
            ['key' => 'api_docs_allowed_domains'],
            ['label' => 'API docs allowed domains', 'value' => $domains, 'type' => 'string'],
        );

        Cache::forget('api_setting:api_docs_allowed_domains');
    }

    /**
     * Evaluate the gate inside a request carrying the given headers, the way a
     * real /docs/api hit would.
     */
    private function gateAllowsWithHeaders(User $user, array $headers): bool
    {
        $request = Request::create('http://submissions.test/docs/api', 'GET', [], [], [], []);

        foreach ($headers as $name => $value) {
            $request->headers->set($name, $value);
        }

        // Symfony caches the resolved host on the request instance.
        $request->server->set('HTTP_HOST', $headers['Host'] ?? 'submissions.test');

        $this->app->instance('request', $request);

        return Gate::forUser($user)->allows('viewApiDocs');
    }

    public function test_a_spoofed_test_host_does_not_grant_access(): void
    {
        $this->allowDomains('nc3.lu');

        $outsider = User::factory()->create(['email' => 'attacker@example.com']);

        $this->assertFalse($this->gateAllowsWithHeaders($outsider, ['Host' => 'test.example.com']));
    }

    public function test_a_spoofed_forwarded_host_does_not_grant_access(): void
    {
        $this->allowDomains('nc3.lu');

        $outsider = User::factory()->create(['email' => 'attacker@example.com']);

        $this->assertFalse($this->gateAllowsWithHeaders($outsider, [
            'X-Forwarded-Host' => 'test.example.com',
        ]));
    }

    public function test_the_real_test_environment_host_does_not_grant_access_by_itself(): void
    {
        $this->allowDomains('nc3.lu');

        $outsider = User::factory()->create(['email' => 'attacker@example.com']);

        $this->assertFalse($this->gateAllowsWithHeaders($outsider, [
            'Host' => 'test.applications.nc3.lu',
        ]));
    }

    public function test_an_allowed_email_domain_grants_access(): void
    {
        $this->allowDomains('nc3.lu');

        $insider = User::factory()->create(['email' => 'analyst@nc3.lu']);

        $this->assertTrue(Gate::forUser($insider)->allows('viewApiDocs'));
    }

    public function test_a_disallowed_email_domain_is_refused(): void
    {
        $this->allowDomains('nc3.lu');

        $outsider = User::factory()->create(['email' => 'analyst@example.com']);

        $this->assertFalse(Gate::forUser($outsider)->allows('viewApiDocs'));
    }

    public function test_no_configured_domains_refuses_everyone(): void
    {
        $this->allowDomains('');

        $user = User::factory()->create(['email' => 'analyst@nc3.lu']);

        $this->assertFalse(Gate::forUser($user)->allows('viewApiDocs'));
    }

    public function test_docs_can_be_opened_to_all_users_by_configuration(): void
    {
        $this->allowDomains('nc3.lu');
        config(['app.api_docs_public' => true]);

        $anyone = User::factory()->create(['email' => 'analyst@example.com']);

        $this->assertTrue(Gate::forUser($anyone)->allows('viewApiDocs'));
    }
}
