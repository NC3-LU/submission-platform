<?php

namespace Tests\Feature;

use App\Livewire\SubmissionForm;
use App\Models\Form;
use App\Models\FormField;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class FileUploadValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Form $form;

    private FormField $field;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');

        $this->user = User::factory()->create(['role' => 'user']);
        $this->form = Form::factory()->for($this->user)->create([
            'status' => 'published',
            'visibility' => 'authenticated',
        ]);
        $category = $this->form->categories()->create(['name' => 'General', 'order' => 1]);
        $this->field = $category->fields()->create([
            'form_id' => $this->form->id,
            'label' => 'Attachment',
            'type' => 'file',
            'required' => false,
            'order' => 1,
        ]);
    }

    private function upload(UploadedFile $file)
    {
        return Livewire::actingAs($this->user)
            ->test(SubmissionForm::class, ['form' => $this->form])
            ->set('tempFiles.field_'.$this->field->id, $file);
    }

    public function test_disallowed_file_type_is_rejected_and_not_stored(): void
    {
        $component = $this->upload(UploadedFile::fake()->create('payload.exe', 16, 'application/octet-stream'));

        $component->assertHasErrors('tempFiles.field_'.$this->field->id);

        // The critical assertion: nothing may reach disk, and no path may be
        // recorded against the field.
        $this->assertEmpty(Storage::disk('private')->files('temp-submissions'));
        $this->assertNull($component->get('fieldValues.'.$this->field->id));
    }

    public function test_php_file_is_rejected(): void
    {
        $component = $this->upload(UploadedFile::fake()->create('shell.php', 4, 'application/x-php'));

        $component->assertHasErrors('tempFiles.field_'.$this->field->id);
        $this->assertEmpty(Storage::disk('private')->files('temp-submissions'));
    }

    public function test_oversized_file_is_rejected(): void
    {
        // MAX_FILE_SIZE is 10240 KB; 12 MB must not be accepted.
        $component = $this->upload(UploadedFile::fake()->create('huge.pdf', 12288, 'application/pdf'));

        $component->assertHasErrors('tempFiles.field_'.$this->field->id);
        $this->assertEmpty(Storage::disk('private')->files('temp-submissions'));
    }

    public function test_allowed_file_type_is_accepted_and_stored(): void
    {
        $component = $this->upload(UploadedFile::fake()->create('report.pdf', 128, 'application/pdf'));

        $component->assertHasNoErrors();
        $this->assertNotEmpty(Storage::disk('private')->files('temp-submissions'));
        $this->assertNotNull($component->get('fieldValues.'.$this->field->id));
    }
}
