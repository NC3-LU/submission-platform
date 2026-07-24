<?php

namespace Tests\Feature\Api;

use App\Models\ApiToken;
use App\Models\Form;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Token abilities are only meaningful if the middleware maps every route to the
 * right one and the token-management endpoints cannot be used to widen a
 * token's own reach.
 */
class ApiTokenAbilityEnforcementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Form $form;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->form = Form::factory()->published()->create(['user_id' => $this->user->id]);

        Submission::factory()->submitted()->create([
            'form_id' => $this->form->id,
            'user_id' => $this->user->id,
        ]);
    }

    /**
     * @param  array<int, string>  $abilities
     */
    private function tokenWith(array $abilities): string
    {
        $plainText = Str::random(40);

        ApiToken::create([
            'user_id' => $this->user->id,
            'name' => 'Scoped token',
            'token' => hash('sha256', $plainText),
            'abilities' => $abilities,
            'allowed_ips' => null,
            'expires_at' => now()->addDay(),
        ]);

        return $plainText;
    }

    private function asToken(string $plainText): self
    {
        return $this->withHeader('Authorization', 'Bearer '.$plainText);
    }

    // --- Submissions abilities are actually reachable ---------------------

    public function test_a_forms_read_token_cannot_read_submissions(): void
    {
        $this->asToken($this->tokenWith(['forms:read']))
            ->getJson("/api/v1/forms/{$this->form->id}/submissions")
            ->assertStatus(403);
    }

    public function test_a_submissions_read_token_can_read_submissions(): void
    {
        $this->asToken($this->tokenWith(['submissions:read']))
            ->getJson("/api/v1/forms/{$this->form->id}/submissions")
            ->assertStatus(200);
    }

    public function test_a_forms_create_token_cannot_create_submissions(): void
    {
        $this->asToken($this->tokenWith(['forms:create']))
            ->postJson("/api/v1/forms/{$this->form->id}/submissions", [])
            ->assertStatus(403);
    }

    public function test_a_forms_read_token_can_still_read_forms(): void
    {
        $this->asToken($this->tokenWith(['forms:read']))
            ->getJson('/api/v1/forms')
            ->assertStatus(200);
    }

    // --- Token management requires its own ability ------------------------

    public function test_a_forms_read_token_cannot_mint_new_tokens(): void
    {
        $this->asToken($this->tokenWith(['forms:read']))
            ->postJson('/api/v1/tokens', ['name' => 'Minted'])
            ->assertStatus(403);

        $this->assertDatabaseMissing('api_tokens', ['name' => 'Minted']);
    }

    public function test_a_forms_read_token_cannot_list_tokens(): void
    {
        $this->asToken($this->tokenWith(['forms:read']))
            ->getJson('/api/v1/tokens')
            ->assertStatus(403);
    }

    // --- No privilege escalation through token creation -------------------

    public function test_a_created_token_does_not_default_to_all_abilities(): void
    {
        $response = $this->asToken($this->tokenWith(['*']))
            ->postJson('/api/v1/tokens', ['name' => 'Defaulted']);

        $response->assertStatus(201);

        $this->assertNotContains('*', $response->json('data.abilities'));
    }

    public function test_a_token_cannot_grant_abilities_it_does_not_hold(): void
    {
        $this->asToken($this->tokenWith(['tokens:manage', 'forms:read']))
            ->postJson('/api/v1/tokens', [
                'name' => 'Escalated',
                'abilities' => ['forms:delete'],
            ])
            ->assertStatus(422);

        $this->assertDatabaseMissing('api_tokens', ['name' => 'Escalated']);
    }

    public function test_a_token_cannot_grant_the_wildcard_ability(): void
    {
        $this->asToken($this->tokenWith(['tokens:manage', 'forms:read']))
            ->postJson('/api/v1/tokens', [
                'name' => 'Wildcarded',
                'abilities' => ['*'],
            ])
            ->assertStatus(422);

        $this->assertDatabaseMissing('api_tokens', ['name' => 'Wildcarded']);
    }

    public function test_unknown_abilities_are_rejected(): void
    {
        $this->asToken($this->tokenWith(['*']))
            ->postJson('/api/v1/tokens', [
                'name' => 'Bogus',
                'abilities' => ['forms:everything'],
            ])
            ->assertStatus(422);
    }

    public function test_a_token_may_grant_abilities_it_holds(): void
    {
        $response = $this->asToken($this->tokenWith(['tokens:manage', 'forms:read']))
            ->postJson('/api/v1/tokens', [
                'name' => 'Narrowed',
                'abilities' => ['forms:read'],
            ]);

        $response->assertStatus(201);
        $this->assertSame(['forms:read'], $response->json('data.abilities'));
    }

    // --- No privilege escalation through token update ---------------------

    public function test_a_token_cannot_widen_its_own_abilities_via_update(): void
    {
        $plainText = $this->tokenWith(['tokens:manage', 'forms:read']);
        $token = ApiToken::where('token', hash('sha256', $plainText))->firstOrFail();

        $this->asToken($plainText)
            ->putJson("/api/v1/tokens/{$token->id}", ['abilities' => ['*']])
            ->assertStatus(422);

        $this->assertNotContains('*', $token->fresh()->abilities);
    }
}
