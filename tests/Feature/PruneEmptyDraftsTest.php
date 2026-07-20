<?php

namespace Tests\Feature;

use App\Models\Form;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PruneEmptyDraftsTest extends TestCase
{
    use RefreshDatabase;

    private function makeForm(): array
    {
        $user = User::factory()->create(['role' => 'user']);
        $form = Form::factory()->for($user)->create(['status' => 'published']);
        $category = $form->categories()->create(['name' => 'General', 'order' => 1]);
        $field = $category->fields()->create([
            'form_id' => $form->id,
            'label' => 'Name',
            'type' => 'text',
            'required' => false,
            'order' => 1,
        ]);

        return [$user, $form, $field];
    }

    public function test_dry_run_reports_but_deletes_nothing(): void
    {
        [$user, $form] = $this->makeForm();
        Submission::create(['form_id' => $form->id, 'user_id' => $user->id, 'status' => 'draft']);

        $this->artisan('app:prune-empty-drafts')->assertSuccessful();

        $this->assertDatabaseCount('submissions', 1);
    }

    public function test_force_deletes_empty_drafts(): void
    {
        [$user, $form] = $this->makeForm();
        Submission::create(['form_id' => $form->id, 'user_id' => $user->id, 'status' => 'draft']);

        $this->artisan('app:prune-empty-drafts --force')->assertSuccessful();

        $this->assertDatabaseCount('submissions', 0);
    }

    public function test_drafts_with_content_are_kept(): void
    {
        [$user, $form, $field] = $this->makeForm();
        $draft = Submission::create(['form_id' => $form->id, 'user_id' => $user->id, 'status' => 'draft']);
        $draft->values()->create(['form_field_id' => $field->id, 'value' => 'Alice']);

        $this->artisan('app:prune-empty-drafts --force')->assertSuccessful();

        $this->assertDatabaseCount('submissions', 1);
    }

    public function test_drafts_with_only_blank_values_are_pruned(): void
    {
        [$user, $form, $field] = $this->makeForm();
        $draft = Submission::create(['form_id' => $form->id, 'user_id' => $user->id, 'status' => 'draft']);
        $draft->values()->create(['form_field_id' => $field->id, 'value' => '']);

        $this->artisan('app:prune-empty-drafts --force')->assertSuccessful();

        $this->assertDatabaseCount('submissions', 0);
    }

    public function test_submitted_submissions_are_never_touched(): void
    {
        [$user, $form] = $this->makeForm();
        Submission::create(['form_id' => $form->id, 'user_id' => $user->id, 'status' => 'submitted']);

        $this->artisan('app:prune-empty-drafts --force')->assertSuccessful();

        $this->assertDatabaseCount('submissions', 1);
    }

    public function test_untouched_only_spares_drafts_that_have_blank_value_rows(): void
    {
        [$user, $form, $field] = $this->makeForm();

        $neverTouched = Submission::create(['form_id' => $form->id, 'user_id' => $user->id, 'status' => 'draft']);

        $blankValues = Submission::create(['form_id' => $form->id, 'user_id' => $user->id, 'status' => 'draft']);
        $blankValues->values()->create(['form_field_id' => $field->id, 'value' => '']);

        $this->artisan('app:prune-empty-drafts --force --untouched-only')->assertSuccessful();

        $this->assertDatabaseMissing('submissions', ['id' => $neverTouched->id]);
        $this->assertDatabaseHas('submissions', ['id' => $blankValues->id]);
    }

    public function test_days_option_spares_recent_drafts(): void
    {
        [$user, $form] = $this->makeForm();
        Submission::create(['form_id' => $form->id, 'user_id' => $user->id, 'status' => 'draft']);

        $this->artisan('app:prune-empty-drafts --force --days=30')->assertSuccessful();

        $this->assertDatabaseCount('submissions', 1);
    }
}
