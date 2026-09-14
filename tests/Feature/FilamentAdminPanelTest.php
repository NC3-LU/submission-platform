<?php

namespace Tests\Feature;

use App\Filament\Resources\ApiTokenResource\Pages\CreateApiToken;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Smoke tests for the Filament admin panel.
 *
 * The panel had no coverage, which made dependency upgrades of Filament
 * unverifiable without clicking through it by hand.
 */
class FilamentAdminPanelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * User::canAccessPanel() requires all three: an @nc3.lu address, a verified
     * email, and the admin role.
     */
    private function admin(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'email' => 'panel-test@nc3.lu',
            'email_verified_at' => now(),
        ]);
    }

    public function test_admin_dashboard_renders(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin')
            ->assertSuccessful();
    }

    public function test_users_resource_renders(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/users')
            ->assertSuccessful();
    }

    public function test_api_tokens_resource_renders(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/api-tokens')
            ->assertSuccessful();
    }

    public function test_api_logs_resource_renders(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/api-logs')
            ->assertSuccessful();
    }

    public function test_non_admin_cannot_reach_the_panel(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($user)->get('/admin');

        $this->assertContains($response->getStatusCode(), [403, 302],
            'A non-admin must not receive a successful admin panel response.');
    }

    public function test_user_editor_never_exposes_two_factor_secrets(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();

        $this->actingAs($admin);

        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->assertFormFieldDoesNotExist('two_factor_secret')
            ->assertFormFieldDoesNotExist('two_factor_recovery_codes');
    }

    public function test_admin_token_creation_uses_the_audited_lifecycle_service(): void
    {
        $admin = $this->admin();
        $owner = User::factory()->create();
        $this->actingAs($admin);

        $component = Livewire::test(CreateApiToken::class)
            ->fillForm([
                'name' => 'Admin issued',
                'user_id' => $owner->id,
                'abilities' => ['forms:read'],
                'allowed_ips' => null,
                'expires_at' => null,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $token = ApiToken::query()->where('name', 'Admin issued')->firstOrFail();
        $this->assertStringStartsWith('nc3_', $component->get('plainTextToken'));
        $this->assertDatabaseHas('api_token_events', [
            'action' => 'created',
            'actor_user_id' => $admin->id,
            'actor_token_id' => null,
            'target_token_id' => $token->id,
        ]);
    }
}
