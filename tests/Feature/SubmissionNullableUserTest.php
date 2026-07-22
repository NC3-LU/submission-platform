<?php

namespace Tests\Feature;

use App\Livewire\SubmissionForm;
use App\Models\Form;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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

        // Guests submit through the Livewire component (no authenticated user).
        Livewire::test(SubmissionForm::class, ['form' => $form])
            ->set('fieldValues.'.$field->id, 'Test Value')
            ->call('submit')
            ->assertRedirect(route('submissions.thankyou'));

        $this->assertDatabaseHas('submissions', [
            'form_id' => $form->id,
            'user_id' => null,
        ]);
        $this->assertDatabaseHas('submission_values', [
            'form_field_id' => $field->id,
            'value' => 'Test Value',
        ]);
    }

    public function test_submission_can_store_ip_address(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $form = Form::factory()->for($user)->create([
            'status' => 'published',
            'visibility' => 'public',
        ]);

        $submission = Submission::create([
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
