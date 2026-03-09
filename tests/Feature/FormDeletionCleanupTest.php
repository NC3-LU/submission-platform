<?php

namespace Tests\Feature;

use App\Models\Form;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FormDeletionCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_form_removes_submission_files_from_storage(): void
    {
        Storage::fake('private');

        $user = User::factory()->create(['role' => 'admin']);
        $form = Form::factory()->for($user)->create(['status' => 'draft']);
        $category = $form->categories()->create(['name' => 'General', 'order' => 1]);
        $field = $category->fields()->create([
            'form_id' => $form->id,
            'label' => 'Document',
            'type' => 'file',
            'required' => false,
            'order' => 1,
        ]);

        $submission = $form->submissions()->create([
            'user_id' => $user->id,
            'status' => 'submitted',
        ]);

        $filePath = "submissions/{$submission->id}/test-document.pdf";
        Storage::disk('private')->put($filePath, 'fake content');

        $submission->values()->create([
            'form_field_id' => $field->id,
            'value' => $filePath,
        ]);

        Storage::disk('private')->assertExists($filePath);

        $form->delete();

        Storage::disk('private')->assertMissing($filePath);
    }
}
