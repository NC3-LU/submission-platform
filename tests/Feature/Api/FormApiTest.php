<?php

namespace Tests\Feature\Api;

use App\Models\ApiToken;
use App\Models\Form;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FormApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected string $token;

    protected ApiToken $apiToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $plainTextToken = Str::random(40);
        $this->token = $plainTextToken;

        $this->apiToken = ApiToken::create([
            'user_id' => $this->user->id,
            'name' => 'Test Token',
            'token' => hash('sha256', $plainTextToken),
            'abilities' => ['*'],
            'expires_at' => now()->addDays(30),
        ]);
    }

    public function test_can_list_forms(): void
    {
        Form::factory()->count(3)->create(['user_id' => $this->user->id]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/v1/forms');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'title', 'description', 'status', 'visibility'],
                ],
            ]);
    }

    public function test_can_create_form(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/forms', [
                'title' => 'Test Form',
                'description' => 'Test Description',
                'status' => 'draft',
                'visibility' => 'private',
                'categories' => [
                    [
                        'name' => 'Category 1',
                        'order' => 1,
                        'fields' => [
                            [
                                'type' => 'text',
                                'label' => 'Field 1',
                                'required' => true,
                                'order' => 1,
                            ],
                        ],
                    ],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => ['id', 'title', 'description', 'status'],
            ]);

        $this->assertDatabaseHas('forms', [
            'title' => 'Test Form',
            'user_id' => $this->user->id,
        ]);
    }

    public function test_can_show_form(): void
    {
        $form = Form::factory()->create(['user_id' => $this->user->id]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson("/api/v1/forms/{$form->id}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $form->id,
                    'title' => $form->title,
                ],
            ]);
    }

    public function test_can_update_form(): void
    {
        $form = Form::factory()->create(['user_id' => $this->user->id]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->putJson("/api/v1/forms/{$form->id}", [
                'title' => 'Updated Title',
                'status' => 'published',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'title' => 'Updated Title',
                    'status' => 'published',
                ],
            ]);
    }

    public function test_can_delete_form(): void
    {
        $form = Form::factory()->create(['user_id' => $this->user->id]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->deleteJson("/api/v1/forms/{$form->id}");

        $response->assertStatus(200)
            ->assertJson(['message' => 'Form deleted successfully']);

        $this->assertDatabaseMissing('forms', ['id' => $form->id]);
    }

    public function test_cannot_access_other_users_form(): void
    {
        $otherUser = User::factory()->create();
        $form = Form::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson("/api/v1/forms/{$form->id}");

        $response->assertStatus(403);
    }

    public function test_validates_form_creation(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/forms', [
                'description' => 'Missing required fields',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'status', 'visibility']);
    }

    public function test_can_filter_forms_by_status(): void
    {
        Form::factory()->create(['user_id' => $this->user->id, 'status' => 'draft']);
        Form::factory()->create(['user_id' => $this->user->id, 'status' => 'published']);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/v1/forms?status=published');

        $response->assertStatus(200);

        $forms = $response->json('data');
        $this->assertCount(1, $forms);
        $this->assertEquals('published', $forms[0]['status']);
    }
}
