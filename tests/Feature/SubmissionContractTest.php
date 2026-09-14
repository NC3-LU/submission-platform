<?php

namespace Tests\Feature;

use App\Livewire\SubmissionForm;
use App\Models\ApiToken;
use App\Models\Form;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SubmissionContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_submission_quota_applies_across_component_instances(): void
    {
        config(['submissions.per_minute' => 1, 'submissions.per_day' => 5]);
        $form = Form::factory()->published()->public()->create();
        Livewire::test(SubmissionForm::class, ['form' => $form])->call('submit')->assertHasNoErrors();
        Livewire::test(SubmissionForm::class, ['form' => $form])->call('submit')->assertHasErrors('quota');
        $this->assertDatabaseCount('submissions', 1);
    }

    public function test_api_status_transition_cannot_publish_another_persons_draft(): void
    {
        $owner = User::factory()->create(['role' => 'internal_evaluator']);
        $form = Form::factory()->create(['user_id' => $owner->id]);
        $draft = Submission::factory()->create(['form_id' => $form->id, 'status' => 'draft']);
        ApiToken::create(['user_id' => $owner->id, 'name' => 'test', 'token' => hash('sha256', 'status-test'), 'abilities' => ['submissions:update']]);
        $this->withToken('status-test')->putJson("/api/v1/forms/{$form->id}/submissions/{$draft->id}", ['status' => 'completed'])->assertForbidden();
        $this->assertSame('draft', $draft->fresh()->status);
    }

    public function test_browser_and_api_ignore_hidden_required_answers(): void
    {
        $owner = User::factory()->create();
        $form = Form::factory()->published()->public()->create(['user_id' => $owner->id]);
        $category = $form->categories()->create(['name' => 'Conditional', 'order' => 1]);
        $parent = $category->fields()->create(['form_id' => $form->id, 'label' => 'Choice', 'type' => 'select', 'options' => 'Yes,No', 'order' => 1]);
        $child = $category->fields()->create(['form_id' => $form->id, 'label' => 'Details', 'type' => 'text', 'required' => true, 'depends_on_field_id' => $parent->id, 'depends_on_value' => 'Yes', 'order' => 2]);
        ApiToken::create(['user_id' => $owner->id, 'name' => 'test', 'token' => hash('sha256', 'conditional-test'), 'abilities' => ['submissions:create']]);
        $this->withToken('conditional-test')->postJson("/api/v1/forms/{$form->id}/submissions", ['values' => [$parent->id => 'No', $child->id => 'hidden value']])->assertCreated();
        Livewire::test(SubmissionForm::class, ['form' => $form])->set('fieldValues', [$parent->id => 'No', $child->id => 'hidden value'])->call('submit')->assertHasNoErrors();
        $this->assertDatabaseMissing('submission_values', ['form_field_id' => $child->id]);
    }
}
