<?php

namespace Tests\Feature;

use App\Livewire\SubmissionForm;
use App\Models\Form;
use App\Models\FormField;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Availability was only enforced by SubmissionController when it rendered the
 * page. Livewire posts straight to the component, so a form left open past
 * available_until could still be submitted.
 */
class SubmissionAvailabilityEnforcementTest extends TestCase
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

    public function test_cannot_submit_after_the_window_closes(): void
    {
        $this->actingAs($this->user);

        $component = Livewire::test(SubmissionForm::class, ['form' => $this->form])
            ->set('fieldValues.'.$this->field->id, 'Late answer');

        // The window closes while the page is open.
        $this->form->update(['available_until' => now()->subMinute()]);

        $component->call('submit');

        $this->assertDatabaseMissing('submissions', [
            'form_id' => $this->form->id,
            'status' => 'submitted',
        ]);
    }

    public function test_cannot_submit_before_the_window_opens(): void
    {
        $this->actingAs($this->user);

        $component = Livewire::test(SubmissionForm::class, ['form' => $this->form])
            ->set('fieldValues.'.$this->field->id, 'Too early');

        $this->form->update(['available_from' => now()->addDay()]);

        $component->call('submit');

        $this->assertDatabaseMissing('submissions', [
            'form_id' => $this->form->id,
            'status' => 'submitted',
        ]);
    }

    public function test_can_submit_inside_the_window(): void
    {
        $this->actingAs($this->user);

        $this->form->update([
            'available_from' => now()->subDay(),
            'available_until' => now()->addDay(),
        ]);

        Livewire::test(SubmissionForm::class, ['form' => $this->form])
            ->set('fieldValues.'.$this->field->id, 'On time')
            ->call('submit');

        $this->assertDatabaseHas('submissions', [
            'form_id' => $this->form->id,
            'status' => 'submitted',
        ]);
    }

    public function test_can_submit_when_no_window_is_configured(): void
    {
        $this->actingAs($this->user);

        Livewire::test(SubmissionForm::class, ['form' => $this->form])
            ->set('fieldValues.'.$this->field->id, 'Anytime')
            ->call('submit');

        $this->assertDatabaseHas('submissions', [
            'form_id' => $this->form->id,
            'status' => 'submitted',
        ]);
    }
}
