<?php

namespace Tests\Feature\Api;

use App\Models\ApiSetting;
use App\Models\ApiToken;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class ApiTokenLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $managerSecret;

    private ApiToken $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->managerSecret = Str::random(40);
        $this->manager = ApiToken::create([
            'user_id' => $this->user->id,
            'name' => 'Manager',
            'token' => hash('sha256', $this->managerSecret),
            'abilities' => ['*'],
        ]);
    }

    public function test_issued_token_is_prefixed_and_creation_response_is_not_cacheable(): void
    {
        $response = $this->withToken($this->managerSecret)
            ->postJson('/api/v1/tokens', [
                'name' => 'Automation',
                'abilities' => ['forms:read'],
            ]);

        $response->assertCreated()
            ->assertHeader('Cache-Control', 'no-store, private');

        $this->assertStringStartsWith('nc3_', $response->json('data.token'));
        $this->assertNull($response->json('data.expires_at'));
        $this->assertDatabaseHas('api_token_events', [
            'action' => 'created',
            'actor_token_id' => $this->manager->id,
            'target_token_id' => $response->json('data.id'),
            'target_token_name' => 'Automation',
        ]);
    }

    public function test_self_revocation_is_recorded_before_the_token_is_deleted(): void
    {
        $this->withToken($this->managerSecret)
            ->deleteJson("/api/v1/tokens/{$this->manager->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('api_tokens', ['id' => $this->manager->id]);
        $this->assertDatabaseHas('api_token_events', [
            'action' => 'revoked',
            'actor_token_id' => $this->manager->id,
            'target_token_id' => $this->manager->id,
            'target_token_name' => 'Manager',
        ]);
        $this->assertDatabaseHas('api_logs', [
            'user_id' => $this->user->id,
            'token_id' => null,
            'method' => 'DELETE',
            'endpoint' => "api/v1/tokens/{$this->manager->id}",
            'response_code' => 204,
        ]);
    }

    public function test_token_can_be_rotated_and_the_old_secret_stops_working(): void
    {
        $response = $this->withToken($this->managerSecret)
            ->postJson("/api/v1/tokens/{$this->manager->id}/rotate");

        $response->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private');

        $replacementSecret = $response->json('data.token');
        $this->assertStringStartsWith('nc3_', $replacementSecret);

        $this->withToken($this->managerSecret)
            ->getJson('/api/v1/tokens')
            ->assertUnauthorized();

        $this->withToken($replacementSecret)
            ->getJson('/api/v1/tokens')
            ->assertOk();

        $this->assertDatabaseHas('api_token_events', [
            'action' => 'rotated',
            'actor_token_id' => $this->manager->id,
            'target_token_id' => $this->manager->id,
        ]);
    }

    public function test_omitted_expiration_uses_the_optional_default_lifetime(): void
    {
        ApiSetting::set('api_token_default_lifetime_days', '7');

        $response = $this->withToken($this->managerSecret)
            ->postJson('/api/v1/tokens', [
                'name' => 'Short lived',
                'abilities' => ['forms:read'],
            ])
            ->assertCreated();

        $expiration = CarbonImmutable::parse($response->json('data.expires_at'));
        $this->assertTrue($expiration->between(now()->addDays(7)->subMinute(), now()->addDays(7)->addMinute()));
    }

    public function test_maximum_lifetime_is_only_enforced_when_configured(): void
    {
        ApiSetting::set('api_token_max_lifetime_days', '30');

        $this->withToken($this->managerSecret)
            ->postJson('/api/v1/tokens', [
                'name' => 'Too long lived',
                'abilities' => ['forms:read'],
                'expires_at' => now()->addDays(31)->toIso8601String(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('expires_at');
    }

    public function test_configured_maximum_lifetime_cannot_be_bypassed_by_updating_a_token(): void
    {
        ApiSetting::set('api_token_max_lifetime_days', '30');

        $this->withToken($this->managerSecret)
            ->patchJson("/api/v1/tokens/{$this->manager->id}", [
                'expires_at' => now()->addDays(31)->toIso8601String(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('expires_at');
    }

    public function test_abilities_cannot_be_updated_to_null(): void
    {
        $this->withToken($this->managerSecret)
            ->patchJson("/api/v1/tokens/{$this->manager->id}", [
                'abilities' => null,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('abilities');

        $this->assertSame(['*'], $this->manager->fresh()->abilities);
    }

    public function test_abilities_column_is_not_nullable(): void
    {
        $abilitiesColumn = collect(Schema::getColumns('api_tokens'))
            ->firstWhere('name', 'abilities');

        $this->assertFalse($abilitiesColumn['nullable']);
    }

    public function test_active_token_limit_is_only_enforced_when_configured(): void
    {
        ApiSetting::set('api_token_max_active_per_user', '2');

        $this->withToken($this->managerSecret)
            ->postJson('/api/v1/tokens', [
                'name' => 'Second active token',
                'abilities' => ['forms:read'],
            ])
            ->assertCreated();

        $this->withToken($this->managerSecret)
            ->postJson('/api/v1/tokens', [
                'name' => 'Over the limit',
                'abilities' => ['forms:read'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_invalid_ip_restrictions_are_rejected(): void
    {
        $this->withToken($this->managerSecret)
            ->postJson('/api/v1/tokens', [
                'name' => 'Malformed restriction',
                'abilities' => ['forms:read'],
                'allowed_ips' => '192.0.2.1, definitely-not-an-ip',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('allowed_ips');
    }

    public function test_ipv4_and_ipv6_cidr_restrictions_are_supported(): void
    {
        $this->manager->update([
            'allowed_ips' => '192.0.2.0/24,2001:db8::/32',
        ]);

        $this->assertTrue($this->manager->isValidIp('192.0.2.42'));
        $this->assertTrue($this->manager->isValidIp('2001:db8::42'));
        $this->assertFalse($this->manager->isValidIp('198.51.100.42'));
    }

    public function test_forwarded_ip_cannot_bypass_restrictions_without_a_trusted_proxy(): void
    {
        $this->manager->update(['allowed_ips' => '203.0.113.10']);

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.20'])
            ->withHeaders([
                'Authorization' => 'Bearer '.$this->managerSecret,
                'X-Forwarded-For' => '203.0.113.10',
            ])
            ->getJson('/api/v1/tokens')
            ->assertForbidden()
            ->assertJson(['message' => 'Access denied from this IP address']);
    }

    public function test_invalid_key_attempts_do_not_lock_out_a_different_valid_key_on_the_same_ip(): void
    {
        Cache::flush();

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->withToken('invalid-key')
                ->getJson('/api/v1/tokens')
                ->assertUnauthorized();
        }

        $this->withToken($this->managerSecret)
            ->getJson('/api/v1/tokens')
            ->assertOk();
    }

    public function test_token_listing_exposes_only_a_safe_fingerprint(): void
    {
        $response = $this->withToken($this->managerSecret)
            ->getJson('/api/v1/tokens')
            ->assertOk()
            ->assertJsonMissingPath('data.0.token');

        $this->assertSame(substr($this->manager->token, 0, 16), $response->json('data.0.fingerprint'));
        $this->assertSame('active', $response->json('data.0.status'));
    }
}
