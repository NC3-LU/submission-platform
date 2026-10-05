<?php

namespace Tests\Feature;

use App\Livewire\SubmissionForm;
use App\Models\Form;
use App\Models\FormField;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FormSectionOrderTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Form $form;

    private FormField $secondField;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->form = Form::factory()->for($this->owner)->published()->public()->create();

        // Recreated or moved sections can have IDs in a different order from
        // their display order. Gaps in the saved order must not skip a step.
        foreach ([1, 3, 2] as $step) {
            $category = $this->form->categories()->create([
                'name' => 'Section '.$step,
                'order' => $step * 10,
            ]);
            $field = $category->fields()->create([
                'form_id' => $this->form->id,
                'label' => 'Question in section '.$step,
                'type' => 'text',
                'required' => $step === 2,
                'order' => 1,
            ]);
            if ($step === 2) {
                $this->secondField = $field;
            }
        }
    }

    public function test_preview_uses_the_saved_section_order(): void
    {
        $this->actingAs($this->owner)
            ->get(route('forms.preview', $this->form))
            ->assertOk()
            ->assertSeeInOrder([
                'Question in section 1',
                'Question in section 2',
                'Question in section 3',
            ]);
    }

    public function test_next_and_previous_keep_sections_in_the_saved_order(): void
    {
        $component = Livewire::test(SubmissionForm::class, ['form' => $this->form]);

        foreach (['nextStep' => 2, 'previousStep' => 1] as $action => $step) {
            $component->call($action)->assertSet('currentStep', $step);
            $this->assertSame(
                ['Section 1', 'Section 2', 'Section 3'],
                $component->get('form')->categories->pluck('name')->all(),
            );
            $this->assertSame('Section '.$step, $component->get('currentStepData')['name']);
        }
    }

    public function test_answer_updates_keep_the_current_section_in_place(): void
    {
        $component = Livewire::test(SubmissionForm::class, ['form' => $this->form])
            ->call('nextStep')
            ->set('fieldValues.'.$this->secondField->id, 'An answer')
            ->assertSet('currentStep', 2);

        $this->assertSame('Section 2', $component->get('form')->categories[1]->name);
    }

    public function test_validation_returns_to_the_correct_section(): void
    {
        Livewire::test(SubmissionForm::class, ['form' => $this->form])
            ->call('nextStep')
            ->call('nextStep')
            ->call('submit')
            ->assertHasErrors(['fieldValues.'.$this->secondField->id => 'required'])
            ->assertSet('currentStep', 2)
            ->assertDispatched('focus-field', id: 'field_'.$this->secondField->id);
    }
}
