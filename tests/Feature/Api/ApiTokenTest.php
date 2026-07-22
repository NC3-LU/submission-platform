<?php

namespace Tests\Feature\Api;

use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ApiTokenTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected string $token;

    protected ApiToken $apiToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        // Create a token for testing
        $plainTextToken = Str::random(40);
        $this->token = $plainTextToken;

        $this->apiToken = ApiToken::create([
            'user_id' => $this->user->id,
            'name' => 'Test Token',
            'token' => hash('sha256', $plainTextToken),
            'abilities' => ['*'],
            'allowed_ips' => null,
            'expires_at' => now()->addDays(30),
        ]);
    }

    public function test_can_list_tokens(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/v1/tokens');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'abilities', 'allowed_ips', 'created_at'],
                ],
            ]);
    }

    public function test_can_create_token(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/tokens', [
                'name' => 'New Test Token',
                'abilities' => ['forms:read', 'forms:create'],
                'expires_at' => now()->addDays(7)->toDateTimeString(),
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => ['id', 'name', 'token', 'abilities', 'expires_at'],
            ])
            ->assertJson([
                'data' => [
                    'name' => 'New Test Token',
                ],
            ]);

        // Verify token is present and plaintext
        $this->assertNotEmpty($response->json('data.token'));
        $this->assertEquals(40, strlen($response->json('data.token')));
    }

    public function test_can_update_token(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->putJson("/api/v1/tokens/{$this->apiToken->id}", [
                'name' => 'Updated Token Name',
                'abilities' => ['forms:read'],
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'name' => 'Updated Token Name',
                ],
            ]);
    }

    public function test_can_delete_token(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->deleteJson("/api/v1/tokens/{$this->apiToken->id}");

        $response->assertStatus(200)
            ->assertJson(['message' => 'API token deleted successfully']);

        $this->assertDatabaseMissing('api_tokens', ['id' => $this->apiToken->id]);
    }

    public function test_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/tokens');
        $response->assertStatus(401);
    }

    public function test_rejects_invalid_token(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer invalid_token_here')
            ->getJson('/api/v1/tokens');

        $response->assertStatus(401)
            ->assertJson(['message' => 'Invalid API token']);
    }

    public function test_rejects_expired_token(): void
    {
        // Create expired token
        $expiredToken = Str::random(40);
        ApiToken::create([
            'user_id' => $this->user->id,
            'name' => 'Expired Token',
            'token' => hash('sha256', $expiredToken),
            'abilities' => ['*'],
            'expires_at' => now()->subDay(),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$expiredToken)
            ->getJson('/api/v1/tokens');

        $response->assertStatus(401)
            ->assertJson(['message' => 'API token has expired']);
    }

    public function test_ip_restriction_works(): void
    {
        // Create token with IP restriction
        $restrictedToken = Str::random(40);
        ApiToken::create([
            'user_id' => $this->user->id,
            'name' => 'IP Restricted Token',
            'token' => hash('sha256', $restrictedToken),
            'abilities' => ['*'],
            'allowed_ips' => '192.168.1.1',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$restrictedToken)
            ->getJson('/api/v1/tokens');

        $response->assertStatus(403)
            ->assertJson(['message' => 'Access denied from this IP address']);
    }

    public function test_tracks_token_usage(): void
    {
        $initialCount = $this->apiToken->usage_count ?? 0;

        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/v1/tokens');

        $this->apiToken->refresh();
        $this->assertGreaterThan($initialCount, $this->apiToken->usage_count);
        $this->assertNotNull($this->apiToken->last_used_at);
    }
}
