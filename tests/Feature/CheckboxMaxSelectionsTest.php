<?php

namespace Tests\Feature;

use App\Livewire\FormFieldManager;
use App\Livewire\SubmissionForm;
use App\Models\ApiToken;
use App\Models\Form;
use App\Models\FormField;
use App\Models\User;
use App\Services\FormDuplicator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CheckboxMaxSelectionsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Form $form;

    private FormField $field;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['role' => 'internal_evaluator']);
        $this->form = Form::factory()->published()->public()->create(['user_id' => $this->owner->id]);
        $category = $this->form->categories()->create(['name' => 'Priorities', 'order' => 1]);
        $this->field = $category->fields()->create([
            'form_id' => $this->form->id,
            'label' => 'Top challenges',
            'type' => 'checkbox',
            'options' => 'Cost,Skills,Time,Tooling,Guidance',
            'max_selections' => 3,
            'order' => 1,
        ]);
    }

    public function test_browser_accepts_selections_up_to_the_limit(): void
    {
        Livewire::test(SubmissionForm::class, ['form' => $this->form])
            ->set('fieldValues', [$this->field->id => [true, true, true, false, false]])
            ->call('submit')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('submission_values', ['form_field_id' => $this->field->id, 'value' => 'Cost, Skills, Time']);
    }

    public function test_browser_rejects_selections_over_the_limit(): void
    {
        Livewire::test(SubmissionForm::class, ['form' => $this->form])
            ->set('fieldValues', [$this->field->id => [true, true, true, true, false]])
            ->call('submit')
            ->assertHasErrors('fieldValues.'.$this->field->id);

        $this->assertDatabaseCount('submissions', 0);
    }

    public function test_api_rejects_selections_over_the_limit(): void
    {
        ApiToken::create(['user_id' => $this->owner->id, 'name' => 'test', 'token' => hash('sha256', 'max-selections'), 'abilities' => ['submissions:create']]);

        $this->withToken('max-selections')
            ->postJson("/api/v1/forms/{$this->form->id}/submissions", ['values' => [$this->field->id => ['Cost', 'Skills', 'Time', 'Tooling']]])
            ->assertUnprocessable();

        $this->withToken('max-selections')
            ->postJson("/api/v1/forms/{$this->form->id}/submissions", ['values' => [$this->field->id => ['Cost', 'Skills']]])
            ->assertCreated();
    }

    public function test_field_without_limit_accepts_every_option(): void
    {
        $this->field->update(['max_selections' => null]);

        Livewire::test(SubmissionForm::class, ['form' => $this->form])
            ->set('fieldValues', [$this->field->id => [true, true, true, true, true]])
            ->call('submit')
            ->assertHasNoErrors();
    }

    public function test_builder_saves_limit_for_checkbox_fields_only(): void
    {
        $draft = Form::factory()->draft()->create(['user_id' => $this->owner->id]);
        $category = $draft->categories()->create(['name' => 'General', 'order' => 1]);
        $builder = Livewire::actingAs($this->owner)->test(FormFieldManager::class, ['form' => $draft]);

        $builder->set('newField.category_id', $category->id)
            ->set('newField.type', 'checkbox')
            ->set('newField.label', 'Pick up to two')
            ->set('newField.options', 'A,B,C')
            ->set('newField.max_selections', 2)
            ->call('addField')
            ->assertHasNoErrors();

        $builder->set('newField.category_id', $category->id)
            ->set('newField.type', 'radio')
            ->set('newField.label', 'Pick one')
            ->set('newField.options', 'A,B')
            ->set('newField.max_selections', 2)
            ->call('addField')
            ->assertHasNoErrors();

        $this->assertSame(2, $draft->fields()->where('type', 'checkbox')->value('max_selections'));
        $this->assertNull($draft->fields()->where('type', 'radio')->value('max_selections'));
    }

    public function test_builder_rejects_a_limit_outside_the_allowed_range(): void
    {
        $draft = Form::factory()->draft()->create(['user_id' => $this->owner->id]);
        $category = $draft->categories()->create(['name' => 'General', 'order' => 1]);

        foreach ([0, 1001, 99999999999] as $limit) {
            Livewire::actingAs($this->owner)->test(FormFieldManager::class, ['form' => $draft])
                ->set('newField.category_id', $category->id)
                ->set('newField.type', 'checkbox')
                ->set('newField.label', 'Pick')
                ->set('newField.options', 'A,B,C')
                ->set('newField.max_selections', $limit)
                ->call('addField')
                ->assertHasErrors('newField.max_selections');
        }

        $this->assertSame(0, $draft->fields()->count());
    }

    public function test_builder_edit_updates_and_clears_the_limit(): void
    {
        $draft = Form::factory()->draft()->create(['user_id' => $this->owner->id]);
        $category = $draft->categories()->create(['name' => 'General', 'order' => 1]);
        $field = $category->fields()->create(['form_id' => $draft->id, 'label' => 'Pick', 'type' => 'checkbox', 'options' => 'A,B,C', 'max_selections' => 2, 'order' => 1]);
        $builder = Livewire::actingAs($this->owner)->test(FormFieldManager::class, ['form' => $draft]);

        $builder->call('editField', $field->id)
            ->assertSet('fieldBeingEdited.max_selections', 2)
            ->set('fieldBeingEdited.max_selections', '')
            ->call('updateField')
            ->assertHasNoErrors();
        $this->assertNull($field->fresh()->max_selections);

        $builder->call('editField', $field->id)
            ->set('fieldBeingEdited.max_selections', '3')
            ->call('updateField')
            ->assertHasNoErrors();
        $this->assertSame(3, $field->fresh()->max_selections);

        $builder->call('editField', $field->id)
            ->set('fieldBeingEdited.type', 'radio')
            ->call('updateField')
            ->assertHasNoErrors();
        $this->assertNull($field->fresh()->max_selections);
    }

    public function test_duplicated_form_keeps_the_limit(): void
    {
        $copy = app(FormDuplicator::class)->duplicate($this->form, $this->owner);

        $this->assertSame(3, $copy->fields()->where('label', 'Top challenges')->value('max_selections'));
    }
}
