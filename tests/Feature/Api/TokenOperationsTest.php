<?php

namespace Tests\Feature\Api;

use App\Models\ApiSetting;
use App\Models\ApiToken;
use App\Models\ApiTokenEvent;
use App\Models\User;
use App\Services\ApiTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

class TokenOperationsTest extends TestCase
{
    use RefreshDatabase;

    private function credential(User $user, array $abilities = ['forms:read']): array
    {
        $secret = Str::random(48);
        $token = ApiToken::create(['user_id' => $user->id, 'name' => 'Integration', 'token' => hash('sha256', $secret), 'abilities' => $abilities]);

        return [$token, $secret];
    }

    private function event(ApiToken $token, string $action, string $at): ApiTokenEvent
    {
        $event = ApiTokenEvent::create(['action' => $action, 'actor_user_id' => $token->user_id, 'actor_token_id' => $token->id, 'target_user_id' => $token->user_id, 'target_token_id' => $token->id, 'target_token_name' => $token->name, 'target_token_fingerprint' => $token->fingerprint(), 'metadata' => ['secret' => 'private-audit-metadata']]);
        $event->forceFill(['created_at' => $at])->save();

        return $event;
    }

    public function test_context_requires_no_management_ability_and_exposes_no_credentials(): void
    {
        ApiSetting::set('rate_limit_api_authenticated', '37');
        $user = User::factory()->create();
        [$token, $secret] = $this->credential($user);

        $response = $this->withToken($secret)->getJson('/api/v1/me')->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.token.id', $token->id)
            ->assertJsonPath('data.token.fingerprint', $token->fingerprint())
            ->assertJsonPath('data.limits.requests_per_minute', 37)
            ->assertJsonMissingPath('data.token.token')
            ->assertJsonMissingPath('data.user.password')
            ->assertHeader('Cache-Control', 'no-store, private');

        $response->assertDontSee($secret)->assertDontSee($token->token);
        $this->assertDatabaseHas('api_token_events', ['action' => 'used', 'target_token_id' => $token->id]);
    }

    public function test_self_revocation_only_revokes_caller_and_is_audited(): void
    {
        $user = User::factory()->create();
        [$token, $secret] = $this->credential($user);
        [$other] = $this->credential($user);
        $this->withToken($secret)->deleteJson('/api/v1/me/token', ['token_id' => $other->id])->assertNoContent();
        $this->assertModelMissing($token);
        $this->assertModelExists($other);
        $this->assertDatabaseHas('api_token_events', ['action' => 'revoked', 'target_token_id' => $token->id, 'actor_token_id' => $token->id]);
        $this->withToken($secret)->getJson('/api/v1/me')->assertUnauthorized();
        $this->withToken($secret)->deleteJson('/api/v1/me/token')->assertUnauthorized();
        $this->assertSame(1, ApiTokenEvent::where('action', 'revoked')->where('target_token_id', $token->id)->count());
    }

    public function test_context_rejects_missing_invalid_and_expired_credentials(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
        $this->withToken('invalid')->getJson('/api/v1/me')->assertUnauthorized();
        [$token, $secret] = $this->credential(User::factory()->create());
        $token->update(['expires_at' => now()->subMinute()]);
        $this->withToken($secret)->getJson('/api/v1/me')->assertUnauthorized();
        $this->withToken($secret)->getJson('/api/v1/me')->assertUnauthorized();
        $this->assertSame(1, ApiTokenEvent::where('action', 'expired')->where('target_token_id', $token->id)->count());
    }

    public function test_context_honors_ip_restrictions_and_the_normal_api_rate_limit(): void
    {
        Cache::flush();
        [$token, $secret] = $this->credential(User::factory()->create());
        $token->update(['allowed_ips' => '192.0.2.10']);
        $this->withToken($secret)->getJson('/api/v1/me')->assertForbidden();
        $token->update(['allowed_ips' => null]);
        ApiSetting::set('rate_limit_api_authenticated', '2');
        $this->withToken($secret)->getJson('/api/v1/me')->assertOk();
        $this->withToken($secret)->getJson('/api/v1/me')->assertOk();
        $this->withToken($secret)->getJson('/api/v1/me')->assertStatus(429)->assertHeader('Retry-After');
    }

    public function test_legacy_sanctum_identity_remains_available_with_deprecation_headers(): void
    {
        $user = User::factory()->create();
        $secret = $user->createToken('Legacy integration')->plainTextToken;
        $this->withToken($secret)->getJson('/api/user')->assertOk()
            ->assertJsonPath('id', $user->id)
            ->assertHeader('Deprecation')
            ->assertHeader('Sunset', 'Thu, 01 Apr 2027 00:00:00 GMT');
    }

    public function test_events_are_scoped_filtered_newest_first_and_keep_deleted_token_snapshots(): void
    {
        [$manager, $secret] = $this->credential(User::factory()->create(), ['tokens:manage']);
        [$other] = $this->credential(User::factory()->create());
        $this->event($manager, 'revoked', '2026-09-01 10:00:00');
        $latest = $this->event($manager, 'revoked', '2026-09-02 10:00:00');
        $this->event($manager, 'created', '2026-09-02 11:00:00');
        $this->event($other, 'revoked', '2026-09-03 10:00:00');
        $response = $this->withToken($secret)->getJson('/api/v1/token-events?action=revoked&from=2026-09-01&to=2026-09-03&per_page=1')
            ->assertOk()->assertJsonPath('meta.total', 2)->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $latest->id)
            ->assertJsonPath('data.0.token.fingerprint', $manager->fingerprint());
        $response->assertDontSee('private-audit-metadata')->assertDontSee($manager->token);
        [$deleted] = $this->credential($manager->user);
        $this->event($deleted, 'revoked', '2026-09-03 12:00:00');
        $deleted->delete();
        $this->withToken($secret)->getJson('/api/v1/token-events?token_id='.$deleted->id)
            ->assertOk()->assertJsonPath('data.0.token.name', 'Integration');
    }

    public function test_event_filters_are_validated_and_management_ability_is_required(): void
    {
        [$token, $secret] = $this->credential(User::factory()->create());
        $this->withToken($secret)->getJson('/api/v1/token-events')->assertForbidden();
        $token->update(['abilities' => ['tokens:manage']]);
        foreach (['per_page=101', 'page=0', 'action=unknown', 'from=2026-09-03&to=2026-09-01', 'token_id=-1'] as $query) {
            $this->withToken($secret)->getJson('/api/v1/token-events?'.$query)->assertUnprocessable();
        }
    }

    public function test_selected_revocation_preserves_caller_and_ignores_foreign_and_missing_ids(): void
    {
        [$caller, $secret] = $this->credential(User::factory()->create(), ['tokens:manage']);
        [$owned] = $this->credential($caller->user);
        [$foreign] = $this->credential(User::factory()->create());
        $this->withToken($secret)->postJson('/api/v1/token-revocations', ['token_ids' => [$caller->id, $owned->id, $foreign->id, 999999]])
            ->assertOk()->assertJsonPath('data.revoked_count', 1)->assertJsonPath('data.current_token_revoked', false);
        $this->assertModelExists($caller);
        $this->assertModelExists($foreign);
        $this->assertModelMissing($owned);
        $this->assertDatabaseHas('api_token_events', ['action' => 'revoked', 'target_token_id' => $owned->id]);
    }

    public function test_caller_revocation_requires_explicit_opt_in(): void
    {
        [$caller, $secret] = $this->credential(User::factory()->create(), ['tokens:manage']);
        $this->withToken($secret)->postJson('/api/v1/token-revocations', ['token_ids' => [$caller->id], 'include_current' => true])
            ->assertOk()->assertJsonPath('data.current_token_revoked', true);
        $this->withToken($secret)->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_revoke_all_is_bounded_and_can_be_repeated_until_complete(): void
    {
        [$caller, $secret] = $this->credential(User::factory()->create(), ['tokens:manage']);
        for ($i = 0; $i < 101; $i++) {
            $this->credential($caller->user);
        }
        $this->withToken($secret)->postJson('/api/v1/token-revocations', ['all_except_current' => true])
            ->assertOk()->assertJsonPath('data.revoked_count', 100)->assertJsonPath('data.has_more', true);
        $this->withToken($secret)->postJson('/api/v1/token-revocations', ['all_except_current' => true])
            ->assertOk()->assertJsonPath('data.revoked_count', 1)->assertJsonPath('data.has_more', false);
        $this->assertModelExists($caller);
        $this->assertSame(101, ApiTokenEvent::where('action', 'revoked')->count());
    }

    public function test_revocation_rejects_invalid_modes_and_requires_management_ability(): void
    {
        [$caller, $secret] = $this->credential(User::factory()->create());
        $this->withToken($secret)->postJson('/api/v1/token-revocations', ['all_except_current' => true])->assertForbidden();
        $caller->update(['abilities' => ['tokens:manage']]);
        foreach ([[], ['token_ids' => []], ['token_ids' => [1, 1]], ['token_ids' => range(1, 101)], ['token_ids' => [1], 'all_except_current' => true], ['all_except_current' => false], ['all_except_current' => true, 'include_current' => true]] as $body) {
            Cache::flush();
            $this->withToken($secret)->postJson('/api/v1/token-revocations', $body)->assertUnprocessable();
        }
    }

    public function test_revocation_rolls_back_all_deletions_when_audit_persistence_fails(): void
    {
        [$caller, $secret] = $this->credential(User::factory()->create(), ['tokens:manage']);
        [$one] = $this->credential($caller->user);
        [$two] = $this->credential($caller->user);
        ApiTokenEvent::creating(function ($event) use ($two) {
            if ($event->action === 'revoked' && $event->target_token_id === $two->id) {
                throw new \RuntimeException('Synthetic audit failure');
            }
        });
        $this->withToken($secret)->postJson('/api/v1/token-revocations', ['token_ids' => [$one->id, $two->id]])->assertStatus(500);
        $this->assertModelExists($one);
        $this->assertModelExists($two);
        $this->assertDatabaseMissing('api_token_events', ['action' => 'revoked', 'target_token_id' => $one->id]);
    }

    public function test_batch_revocation_has_a_stricter_per_user_rate_limit(): void
    {
        Cache::flush();
        [$caller, $secret] = $this->credential(User::factory()->create(), ['tokens:manage']);
        for ($i = 0; $i < 5; $i++) {
            $this->withToken($secret)->postJson('/api/v1/token-revocations', ['all_except_current' => true])->assertOk();
        }
        $this->withToken($secret)->postJson('/api/v1/token-revocations', ['all_except_current' => true])->assertStatus(429)->assertHeader('Retry-After');
    }

    public function test_stale_revocation_does_not_duplicate_audit_events(): void
    {
        [$caller] = $this->credential(User::factory()->create(), ['tokens:manage']);
        [$target] = $this->credential($caller->user);
        $stale = $target->fresh();
        $service = app(ApiTokenService::class);
        $this->assertTrue($service->revoke($target, actorToken: $caller));
        $this->assertFalse($service->revoke($stale, actorToken: $caller));
        $this->assertSame(1, ApiTokenEvent::where('action', 'revoked')->where('target_token_id', $target->id)->count());
    }
}
