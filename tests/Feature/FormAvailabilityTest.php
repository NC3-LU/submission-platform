<?php

namespace Tests\Feature;

use App\Models\Form;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_form_before_available_from_rejects_submissions(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $form = Form::factory()->for($user)->create([
            'status' => 'published',
            'visibility' => 'public',
            'available_from' => now()->addDays(7),
        ]);

        $response = $this->get(route('submissions.create', $form));

        $response->assertStatus(403);
    }

    public function test_form_after_available_until_rejects_submissions(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $form = Form::factory()->for($user)->create([
            'status' => 'published',
            'visibility' => 'public',
            'available_until' => now()->subDays(1),
        ]);

        $response = $this->get(route('submissions.create', $form));

        $response->assertStatus(403);
    }

    public function test_form_within_availability_window_allows_access(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $form = Form::factory()->for($user)->create([
            'status' => 'published',
            'visibility' => 'public',
            'available_from' => now()->subDays(1),
            'available_until' => now()->addDays(7),
        ]);
        $form->categories()->create(['name' => 'General', 'order' => 1]);

        $response = $this->get(route('submissions.create', $form));

        $response->assertStatus(200);
    }

    public function test_form_with_no_dates_allows_access(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $form = Form::factory()->for($user)->create([
            'status' => 'published',
            'visibility' => 'public',
        ]);
        $form->categories()->create(['name' => 'General', 'order' => 1]);

        $response = $this->get(route('submissions.create', $form));

        $response->assertStatus(200);
    }
}
