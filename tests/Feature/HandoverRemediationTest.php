<?php

namespace Tests\Feature;

use App\Actions\Jetstream\DeleteUser;
use App\Livewire\SubmissionForm;
use App\Models\ApiSetting;
use App\Models\ApiToken;
use App\Models\Form;
use App\Models\Submission;
use App\Models\SubmissionValues;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class HandoverRemediationTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(string $type = 'text', array $attrs = []): array
    {
        $owner = User::factory()->create(['role' => 'internal_evaluator']);
        $form = Form::factory()->published()->public()->create(['user_id' => $owner->id]);
        $cat = $form->categories()->create(['name' => 'Audit', 'order' => 1]);
        $field = $cat->fields()->create(array_merge(['form_id' => $form->id, 'type' => $type, 'label' => 'Audit answer', 'required' => false, 'order' => 1], $attrs));

        return [$owner, $form, $field];
    }

    public function test_forged_temp_path_must_not_move_another_submissions_file(): void
    {
        Storage::fake('private');
        [$owner,$form,$field] = $this->fixture('file');
        $victim = Submission::factory()->submitted()->create(['form_id' => $form->id]);
        $path = "submissions/{$victim->id}/audit-secret.pdf";
        Storage::disk('private')->put($path, 'synthetic victim contents');
        Livewire::test(SubmissionForm::class, ['form' => $form])->assertOk()->set("fieldValues.{$field->id}", 'temp-submissions/../'.$path)->call('submit');
        Storage::disk('private')->assertExists($path);
    }

    public function test_browser_edit_flag_must_not_bypass_closed_form(): void
    {
        [$owner,$form,$field] = $this->fixture();
        $component = Livewire::test(SubmissionForm::class, ['form' => $form]);
        $form->update(['available_until' => now()->subMinute()]);
        $component->set('isEditMode', true)->set("fieldValues.{$field->id}", 'late answer')->call('submit');
        $this->assertDatabaseMissing('submissions', ['form_id' => $form->id, 'status' => 'submitted']);
    }

    public function test_submissions_must_stop_when_open_form_becomes_private(): void
    {
        [$owner,$form,$field] = $this->fixture();
        $component = Livewire::test(SubmissionForm::class, ['form' => $form]);
        $form->update(['visibility' => 'private']);
        $component->set("fieldValues.{$field->id}", 'now unauthorized')->call('submit');
        $this->assertDatabaseMissing('submissions', ['form_id' => $form->id, 'status' => 'submitted']);
    }

    public function test_stale_draft_must_not_overwrite_submitted_answers(): void
    {
        [$owner,$form,$field] = $this->fixture();
        $user = User::factory()->create();
        $draft = Submission::factory()->create(['form_id' => $form->id, 'user_id' => $user->id, 'status' => 'draft']);
        $draft->values()->create(['form_field_id' => $field->id, 'value' => 'original']);
        $component = Livewire::actingAs($user)->test(SubmissionForm::class, ['form' => $form, 'submission' => $draft]);
        $draft->update(['status' => 'submitted']);
        $component->set("fieldValues.{$field->id}", 'overwritten after submission')->call('saveAsDraft');
        $this->assertSame('original', $draft->values()->first()->value);
    }

    public function test_repeat_autosave_must_preserve_file_reference(): void
    {
        Storage::fake('private');
        [$owner,$form,$field] = $this->fixture('file');
        $user = User::factory()->create();
        $component = Livewire::actingAs($user)->test(SubmissionForm::class, ['form' => $form])->assertOk()->set("tempFiles.field_{$field->id}", UploadedFile::fake()->create('audit.pdf', 10, 'application/pdf'))->call('saveAsDraft');
        $first = SubmissionValues::where('form_field_id', $field->id)->firstOrFail()->value;
        $component->call('saveAsDraft');
        $second = SubmissionValues::where('form_field_id', $field->id)->firstOrFail()->value;
        $this->assertTrue(Storage::disk('private')->exists($second), "After second autosave, DB points to missing file: $second; first path: $first");
    }

    public function test_required_checkbox_must_have_selection(): void
    {
        [$owner,$form,$field] = $this->fixture('checkbox', ['required' => true, 'options' => 'Alpha,Beta']);
        Livewire::test(SubmissionForm::class, ['form' => $form])->assertOk()->set("fieldValues.{$field->id}", [false, false])->call('submit');
        $this->assertDatabaseMissing('submissions', ['form_id' => $form->id, 'status' => 'submitted']);
    }

    public function test_json_export_must_preserve_checkbox_selections(): void
    {
        [$owner,$form,$field] = $this->fixture('checkbox', ['options' => 'Alpha,Beta']);
        $submission = Submission::factory()->submitted()->create(['form_id' => $form->id, 'user_id' => $owner->id]);
        $submission->values()->create(['form_field_id' => $field->id, 'value' => 'Alpha, Beta']);
        $this->actingAs($owner)->getJson(route('submissions.export.single.json', [$form, $submission]))->assertOk()->assertJsonPath('categories.0.fields.0.value', 'Alpha, Beta');
    }

    public function test_api_must_not_reveal_someone_elses_draft(): void
    {
        [$owner,$form,$field] = $this->fixture();
        $evaluator = User::factory()->create(['role' => 'external_evaluator']);
        $form->appointedUsers()->attach($evaluator, ['can_edit' => false]);
        $draft = Submission::factory()->create(['form_id' => $form->id, 'status' => 'draft']);
        $draft->values()->create(['form_field_id' => $field->id, 'value' => 'unsubmitted confidential draft']);
        $this->assertFalse(Gate::forUser($evaluator)->allows('view', $draft));
        ApiToken::create(['user_id' => $evaluator->id, 'name' => 'audit', 'token' => hash('sha256', 'synthetic-audit-token'), 'abilities' => ['submissions:read']]);
        $this->withToken('synthetic-audit-token')->getJson("/api/v1/forms/{$form->id}/submissions/{$draft->id}")->assertForbidden();
    }

    public function test_rate_limit_response_must_be_429(): void
    {
        [$owner,$form,$field] = $this->fixture();
        ApiToken::create(['user_id' => $owner->id, 'name' => 'audit', 'token' => hash('sha256', 'synthetic-audit-token'), 'abilities' => ['forms:read']]);
        ApiSetting::set('rate_limit_api_authenticated', 1);
        $this->withToken('synthetic-audit-token')->getJson('/api/v1/forms')->assertOk();
        $this->withToken('synthetic-audit-token')->getJson('/api/v1/forms')
            ->assertStatus(429)->assertHeader('Retry-After');
    }

    public function test_deleting_owner_must_not_destroy_others_submissions(): void
    {
        [$owner,$form,$field] = $this->fixture();
        $submission = Submission::factory()->submitted()->create(['form_id' => $form->id, 'user_id' => User::factory()->create()->id]);
        $submission->values()->create(['form_field_id' => $field->id, 'value' => 'another users report']);
        try {
            (new DeleteUser)->delete($owner);
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('ownership', $exception->errors());
        }
        $this->assertDatabaseHas('submissions', ['id' => $submission->id]);
    }

    public function test_deleting_used_field_must_preserve_submitted_answers(): void
    {
        [$owner,$form,$field] = $this->fixture();
        $submission = Submission::factory()->submitted()->create(['form_id' => $form->id]);
        $answer = $submission->values()->create(['form_field_id' => $field->id, 'value' => 'historical answer']);
        $this->actingAs($owner)->delete(route('forms.fields.destroy', [$form, $field]))->assertRedirect();
        $this->assertDatabaseHas('submission_values', ['id' => $answer->id]);
    }

    public function test_api_form_deletion_must_remove_owned_files(): void
    {
        Storage::fake('private');
        [$owner,$form,$field] = $this->fixture('file');
        $submission = Submission::factory()->submitted()->create(['form_id' => $form->id]);
        $path = "submissions/{$submission->id}/audit-orphan.pdf";
        Storage::disk('private')->put($path, 'synthetic attachment');
        $submission->values()->create(['form_field_id' => $field->id, 'value' => $path]);
        ApiToken::create(['user_id' => $owner->id, 'name' => 'audit', 'token' => hash('sha256', 'synthetic-delete-token'), 'abilities' => ['forms:delete']]);
        $this->withToken('synthetic-delete-token')->deleteJson("/api/v1/forms/{$form->id}")->assertOk();
        Storage::disk('private')->assertMissing($path);
    }

    public function test_api_checkbox_must_accept_same_selection_shape_as_browser(): void
    {
        [$owner,$form,$field] = $this->fixture('checkbox', ['options' => 'Alpha,Beta']);
        ApiToken::create(['user_id' => $owner->id, 'name' => 'audit', 'token' => hash('sha256', 'synthetic-submit-token'), 'abilities' => ['submissions:create']]);
        $this->withToken('synthetic-submit-token')->postJson("/api/v1/forms/{$form->id}/submissions", ['values' => [$field->id => [true, false]]])->assertCreated();
    }

    public function test_json_export_query_count_should_not_grow_per_submission(): void
    {
        [$owner,$form,$field] = $this->fixture();
        $this->actingAs($owner);
        Submission::factory()->submitted()->create(['form_id' => $form->id, 'user_id' => $owner->id]);
        $run = function () use ($form) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $r = $this->get(route('submissions.export.form.json', $form));
            $r->streamedContent();
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };
        $one = $run();
        Submission::factory()->count(4)->submitted()->create(['form_id' => $form->id, 'user_id' => $owner->id]);
        $five = $run();
        $this->assertLessThanOrEqual($one + 3, $five, "One submission: $one SQL queries; five submissions: $five SQL queries");
    }
}
