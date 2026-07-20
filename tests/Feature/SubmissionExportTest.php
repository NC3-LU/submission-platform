<?php

namespace Tests\Feature;

use App\Models\Form;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubmissionExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_export_own_submission_as_json(): void
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

        $submission = $form->submissions()->create([
            'user_id' => $user->id,
            'status' => 'submitted',
        ]);
        $submission->values()->create([
            'form_field_id' => $field->id,
            'value' => 'Test Value',
        ]);

        $this->actingAs($user);

        $response = $this->get(route('submissions.export.single.json', [
            'form' => $form->id,
            'submission' => $submission->id,
        ]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/json');
    }

    public function test_user_can_export_submission_with_status_metadata_as_json(): void
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

        $submission = $form->submissions()->create([
            'user_id' => $user->id,
            'status' => 'submitted',
            'status_metadata' => ['reviewer' => 'admin', 'notes' => 'Approved'],
        ]);
        $submission->values()->create([
            'form_field_id' => $field->id,
            'value' => 'Test Value',
        ]);

        $this->actingAs($user);

        $response = $this->get(route('submissions.export.single.json', [
            'form' => $form->id,
            'submission' => $submission->id,
        ]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/json');

        $data = $response->json();
        $this->assertIsArray($data['status_metadata']);
        $this->assertEquals('admin', $data['status_metadata']['reviewer']);
    }
}
