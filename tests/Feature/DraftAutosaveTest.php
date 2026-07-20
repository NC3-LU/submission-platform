<?php

namespace Tests\Feature;

use App\Livewire\SubmissionForm;
use App\Models\Form;
use App\Models\FormField;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DraftAutosaveTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Form $form;

    private FormField $field;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'user']);
        $this->form = Form::factory()->for($this->user)->create([
            'status' => 'published',
            'visibility' => 'authenticated',
        ]);
        $category = $this->form->categories()->create(['name' => 'General', 'order' => 1]);
        $this->field = $category->fields()->create([
            'form_id' => $this->form->id,
            'label' => 'Name',
            'type' => 'text',
            'required' => false,
            'order' => 1,
        ]);
    }

    public function test_opening_a_form_does_not_create_a_draft(): void
    {
        $this->actingAs($this->user);

        Livewire::test(SubmissionForm::class, ['form' => $this->form]);

        $this->assertDatabaseCount('submissions', 0);
    }

    public function test_autosave_with_no_input_does_not_create_a_draft(): void
    {
        $this->actingAs($this->user);

        Livewire::test(SubmissionForm::class, ['form' => $this->form])
            ->call('autosaveDraft')
            ->call('autosaveDraft');

        $this->assertDatabaseCount('submissions', 0);
    }

    public function test_autosave_does_not_dispatch_a_notification(): void
    {
        $this->actingAs($this->user);

        Livewire::test(SubmissionForm::class, ['form' => $this->form])
            ->set('fieldValues.'.$this->field->id, 'Alice')
            ->call('autosaveDraft')
            ->assertNotDispatched('success');
    }

    public function test_autosave_persists_a_draft_once_there_is_content(): void
    {
        $this->actingAs($this->user);

        Livewire::test(SubmissionForm::class, ['form' => $this->form])
            ->set('fieldValues.'.$this->field->id, 'Alice')
            ->call('autosaveDraft');

        $this->assertDatabaseCount('submissions', 1);
        $this->assertDatabaseHas('submissions', [
            'form_id' => $this->form->id,
            'user_id' => $this->user->id,
            'status' => 'draft',
        ]);
        $this->assertDatabaseHas('submission_values', [
            'form_field_id' => $this->field->id,
            'value' => 'Alice',
        ]);
    }

    public function test_repeated_autosaves_reuse_the_same_draft_row(): void
    {
        $this->actingAs($this->user);

        $component = Livewire::test(SubmissionForm::class, ['form' => $this->form])
            ->set('fieldValues.'.$this->field->id, 'Alice')
            ->call('autosaveDraft')
            ->set('fieldValues.'.$this->field->id, 'Alice B')
            ->call('autosaveDraft')
            ->call('autosaveDraft');

        $this->assertDatabaseCount('submissions', 1);
        $this->assertDatabaseHas('submission_values', [
            'form_field_id' => $this->field->id,
            'value' => 'Alice B',
        ]);

        $component->assertOk();
    }

    public function test_manual_save_draft_still_notifies_the_user(): void
    {
        $this->actingAs($this->user);

        Livewire::test(SubmissionForm::class, ['form' => $this->form])
            ->set('fieldValues.'.$this->field->id, 'Alice')
            ->call('saveAsDraft')
            ->assertDispatched('success');

        $this->assertDatabaseCount('submissions', 1);
    }

    public function test_manual_save_draft_with_no_content_does_not_create_a_row(): void
    {
        $this->actingAs($this->user);

        Livewire::test(SubmissionForm::class, ['form' => $this->form])
            ->call('saveAsDraft');

        $this->assertDatabaseCount('submissions', 0);
    }

    public function test_existing_draft_is_reused_on_remount(): void
    {
        $this->actingAs($this->user);

        Livewire::test(SubmissionForm::class, ['form' => $this->form])
            ->set('fieldValues.'.$this->field->id, 'Alice')
            ->call('autosaveDraft');

        Livewire::test(SubmissionForm::class, ['form' => $this->form])
            ->set('fieldValues.'.$this->field->id, 'Alice again')
            ->call('autosaveDraft');

        $this->assertDatabaseCount('submissions', 1);
    }

    public function test_guests_never_persist_drafts(): void
    {
        Livewire::test(SubmissionForm::class, ['form' => $this->form])
            ->set('fieldValues.'.$this->field->id, 'Anon')
            ->call('autosaveDraft');

        $this->assertDatabaseCount('submissions', 0);
    }
}
