<?php

namespace Tests\Feature;

use App\Livewire\SubmissionForm;
use App\Models\Form;
use App\Models\FormField;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CheckboxInitialStateTest extends TestCase
{
    use RefreshDatabase;

    private Form $form;

    private FormField $field;

    protected function setUp(): void
    {
        parent::setUp();

        $this->form = Form::factory()->published()->public()->create();
        $category = $this->form->categories()->create(['name' => 'General', 'order' => 1]);
        $this->field = $category->fields()->create([
            'form_id' => $this->form->id,
            'label' => 'Topics',
            'type' => 'checkbox',
            'options' => 'Security,Usability,Privacy',
            'order' => 1,
        ]);
    }

    public function test_unanswered_checkbox_question_starts_with_every_option_unticked(): void
    {
        Livewire::test(SubmissionForm::class, ['form' => $this->form])
            ->assertSet('fieldValues.'.$this->field->id, [false, false, false]);
    }

    public function test_saved_checkbox_answers_are_not_overwritten(): void
    {
        $user = User::factory()->create();
        $draft = Submission::factory()->create(['form_id' => $this->form->id, 'user_id' => $user->id, 'status' => 'draft']);
        $draft->values()->create(['form_field_id' => $this->field->id, 'value' => 'Security, Privacy']);

        Livewire::actingAs($user)->test(SubmissionForm::class, ['form' => $this->form, 'submission' => $draft])
            ->assertSet('fieldValues.'.$this->field->id, [true, false, true]);
    }

    public function test_untouched_optional_checkbox_question_stores_an_empty_answer(): void
    {
        Livewire::test(SubmissionForm::class, ['form' => $this->form])
            ->call('submit')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('submissions', 1);
        $this->assertDatabaseHas('submission_values', ['form_field_id' => $this->field->id, 'value' => null]);
    }

    public function test_untouched_required_checkbox_question_is_still_rejected(): void
    {
        $this->field->update(['required' => true]);

        Livewire::test(SubmissionForm::class, ['form' => $this->form])
            ->call('submit')
            ->assertHasErrors('fieldValues.'.$this->field->id);

        $this->assertDatabaseCount('submissions', 0);
    }

    public function test_opening_a_form_with_checkbox_questions_does_not_create_a_draft(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(SubmissionForm::class, ['form' => $this->form])
            ->call('autosaveDraft');

        $this->assertDatabaseCount('submissions', 0);
    }
}
