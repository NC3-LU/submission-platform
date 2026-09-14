<?php

namespace Tests\Feature;

use App\Actions\Jetstream\DeleteUser;
use App\Livewire\SubmissionForm;
use App\Models\Form;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class HandoverDataIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_submitter_anonymizes_and_preserves_response(): void
    {
        $user = User::factory()->create();
        $submission = Submission::factory()->submitted()->create(['user_id' => $user->id]);
        (new DeleteUser)->delete($user);
        $this->assertDatabaseHas('submissions', ['id' => $submission->id, 'user_id' => null, 'status' => 'submitted']);
    }

    public function test_transfer_command_preserves_form_and_answers_and_allows_departing_owner_deletion(): void
    {
        $owner = User::factory()->create(['role' => 'internal_evaluator']);
        $successor = User::factory()->create(['role' => 'internal_evaluator']);
        $form = Form::factory()->create(['user_id' => $owner->id]);
        $submission = Submission::factory()->submitted()->create(['form_id' => $form->id]);
        $this->artisan('app:transfer-form-ownership', ['from' => $owner->email, 'to' => $successor->email, '--dry-run' => true])->assertSuccessful();
        $this->assertEquals($owner->id, $form->fresh()->user_id);
        $this->artisan('app:transfer-form-ownership', ['from' => $owner->email, 'to' => $successor->email])->assertSuccessful();
        (new DeleteUser)->delete($owner);
        $this->assertEquals($successor->id, $form->fresh()->user_id);
        $this->assertDatabaseHas('submissions', ['id' => $submission->id]);
    }

    public function test_used_structure_cannot_be_changed_directly(): void
    {
        $form = Form::factory()->create();
        $category = $form->categories()->create(['name' => 'Original', 'order' => 1]);
        Submission::factory()->submitted()->create(['form_id' => $form->id]);
        try {
            $category->update(['name' => 'Changed meaning']);
            $this->fail('Structure mutation must be rejected');
        } catch (ValidationException) {
            $this->assertSame('Original', $category->fresh()->name);
        }
    }

    public function test_replaying_guest_submission_creates_one_response(): void
    {
        $form = Form::factory()->published()->public()->create();
        $component = Livewire::test(SubmissionForm::class, ['form' => $form]);
        $snapshot = $component->snapshot;
        $payload = ['components' => [[
            'snapshot' => json_encode($snapshot), 'updates' => [],
            'calls' => [['path' => '', 'method' => 'submit', 'params' => []]],
        ]]];
        $this->withHeader('X-Livewire', 'true')->postJson(Livewire::getUpdateUri(), $payload)->assertOk();
        $this->withHeader('X-Livewire', 'true')->postJson(Livewire::getUpdateUri(), $payload)->assertOk();
        $this->assertDatabaseCount('submissions', 1);
        $this->get('/');
        $this->get(route('submissions.thankyou'))->assertSee(Submission::firstOrFail()->id);
    }

    public function test_every_supported_status_round_trips_without_resetting_answers(): void
    {
        $submission = Submission::factory()->submitted()->create();
        foreach (Submission::STATUSES as $status) {
            $submission->update(['status' => $status]);
            $this->assertSame($status, $submission->fresh()->status);
        }
    }

    public function test_revoked_private_link_cannot_submit_from_a_stale_component(): void
    {
        $form = Form::factory()->published()->private()->create();
        $link = $form->accessLinks()->create(['token' => 'synthetic-revocable-link']);
        $this->withSession(['form_access_'.$form->id => ['token' => $link->token]]);
        $component = Livewire::test(SubmissionForm::class, ['form' => $form]);
        $link->delete();
        $component->call('submit')->assertHasErrors('availability');
        $this->assertDatabaseCount('submissions', 0);
    }

    public function test_database_constraint_blocks_cascading_form_owner_deletion(): void
    {
        $form = Form::factory()->create();
        try {
            DB::table('users')->where('id', $form->user_id)->delete();
            $this->fail('Database must reject cascading form ownership deletion');
        } catch (QueryException) {
            $this->assertDatabaseHas('forms', ['id' => $form->id]);
        }
    }
}
