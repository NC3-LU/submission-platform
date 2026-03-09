<?php

namespace Tests\Feature;

use App\Models\Form;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubmissionNullableUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_create_submission_on_public_form(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $form = Form::factory()->for($user)->create([
            'status' => 'published',
            'visibility' => 'public',
        ]);
        $category = $form->categories()->create(['name' => 'General', 'order' => 1]);
        $field = $category->fields()->create([
            'form_id' => $form->id,
            'label' => 'Name',
            'type' => 'text',
            'required' => false,
            'order' => 1,
        ]);

        $response = $this->post(route('submissions.store', $form), [
            'field_' . $field->id => 'Test Value',
        ]);

        $response->assertRedirect(route('submissions.thankyou'));
        $this->assertDatabaseHas('submissions', [
            'form_id' => $form->id,
            'user_id' => null,
        ]);
    }

    public function test_submission_can_store_ip_address(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $form = Form::factory()->for($user)->create([
            'status' => 'published',
            'visibility' => 'public',
        ]);

        $submission = \App\Models\Submission::create([
            'form_id' => $form->id,
            'user_id' => $user->id,
            'ip_address' => '192.168.1.1',
            'status' => 'submitted',
        ]);

        $this->assertDatabaseHas('submissions', [
            'id' => $submission->id,
            'ip_address' => '192.168.1.1',
        ]);
    }
}
