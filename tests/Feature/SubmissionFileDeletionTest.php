<?php

namespace Tests\Feature;

use App\Livewire\SubmissionForm;
use App\Models\Form;
use App\Models\FormCategory;
use App\Models\FormField;
use App\Models\Submission;
use App\Models\SubmissionValues;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * `fieldValues` is a plain public Livewire property, so its contents come from
 * the browser on every request. deleteFile() must therefore treat the path it
 * finds there as untrusted input rather than as something this component put
 * on disk itself.
 */
class SubmissionFileDeletionTest extends TestCase
{
    use RefreshDatabase;

    private Form $form;

    private FormField $fileField;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');

        $owner = User::factory()->create();

        $this->form = Form::factory()->published()->public()->create(['user_id' => $owner->id]);

        $category = FormCategory::create([
            'form_id' => $this->form->id,
            'name' => 'Uploads',
            'order' => 1,
        ]);

        $this->fileField = FormField::create([
            'form_id' => $this->form->id,
            'form_category_id' => $category->id,
            'type' => 'file',
            'label' => 'Attachment',
            'order' => 1,
        ]);
    }

    /**
     * A file belonging to somebody else's submission, as its download link
     * would disclose it.
     */
    private function foreignFile(): string
    {
        $victim = Submission::factory()->submitted()->create([
            'form_id' => $this->form->id,
            'user_id' => User::factory()->create()->id,
        ]);

        $path = "submissions/{$victim->id}/secret.pdf";
        Storage::disk('private')->put($path, 'victim contents');

        SubmissionValues::create([
            'submission_id' => $victim->id,
            'form_field_id' => $this->fileField->id,
            'value' => $path,
        ]);

        return $path;
    }

    public function test_a_file_from_another_submission_cannot_be_deleted(): void
    {
        $foreign = $this->foreignFile();

        Livewire::test(SubmissionForm::class, ['form' => $this->form])
            ->set("fieldValues.{$this->fileField->id}", $foreign)
            ->call('deleteFile', $this->fileField->id);

        Storage::disk('private')->assertExists($foreign);
    }

    public function test_an_arbitrary_private_disk_path_cannot_be_deleted(): void
    {
        Storage::disk('private')->put('exports/quarterly-report.pdf', 'unrelated');

        Livewire::test(SubmissionForm::class, ['form' => $this->form])
            ->set("fieldValues.{$this->fileField->id}", 'exports/quarterly-report.pdf')
            ->call('deleteFile', $this->fileField->id);

        Storage::disk('private')->assertExists('exports/quarterly-report.pdf');
    }

    public function test_a_file_uploaded_in_this_session_can_be_deleted(): void
    {
        $component = Livewire::test(SubmissionForm::class, ['form' => $this->form])
            ->set("tempFiles.field_{$this->fileField->id}", UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf'));

        $path = $component->get("fieldValues.{$this->fileField->id}");

        $this->assertIsString($path);
        Storage::disk('private')->assertExists($path);

        $component->call('deleteFile', $this->fileField->id);

        Storage::disk('private')->assertMissing($path);
    }

    public function test_a_file_already_attached_to_the_own_draft_can_be_deleted(): void
    {
        $user = User::factory()->create();

        $draft = Submission::factory()->create([
            'form_id' => $this->form->id,
            'user_id' => $user->id,
            'status' => 'draft',
        ]);

        $path = "submissions/{$draft->id}/mine.pdf";
        Storage::disk('private')->put($path, 'my contents');

        SubmissionValues::create([
            'submission_id' => $draft->id,
            'form_field_id' => $this->fileField->id,
            'value' => $path,
        ]);

        Livewire::actingAs($user)
            ->test(SubmissionForm::class, ['form' => $this->form, 'submission' => $draft])
            ->call('deleteFile', $this->fileField->id);

        Storage::disk('private')->assertMissing($path);
    }
}
