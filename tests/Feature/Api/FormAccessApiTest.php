<?php

namespace Tests\Feature\Api;

use App\Models\ApiToken;
use App\Models\Form;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FormAccessApiTest extends TestCase
{
    use RefreshDatabase;

    private Form $form;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->form = Form::factory()->create(['user_id' => $user->id]);
        $this->token = Str::random(40);

        ApiToken::create([
            'user_id' => $user->id,
            'name' => 'Form manager',
            'token' => hash('sha256', $this->token),
            'abilities' => ['forms:update'],
            'expires_at' => now()->addDay(),
        ]);
    }

    public function test_creates_an_access_link_without_phantom_database_fields(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson("/api/v1/form/{$this->form->id}/access-links", [
                'expires_at' => now()->addHour()->toIso8601String(),
            ]);

        $response->assertCreated()
            ->assertJsonStructure(['data' => ['id', 'form_id', 'token', 'expires_at']])
            ->assertJsonMissingPath('data.name')
            ->assertJsonMissingPath('data.max_submissions')
            ->assertJsonMissingPath('data.submission_count');
    }
}
