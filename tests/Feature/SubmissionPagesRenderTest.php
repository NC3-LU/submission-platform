<?php

namespace Tests\Feature;

use App\Models\Form;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Smoke tests that actually render the submission pages.
 *
 * The edit page shipped in v2.0.0 referencing route('submissions.user_index'),
 * which does not exist, producing a 500. Nothing rendered these views in the
 * suite, so an undefined route name in a Blade template reached production.
 */
class SubmissionPagesRenderTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Form $form;

    private Submission $submission;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['role' => 'admin']);
        $this->form = Form::factory()->for($this->owner)->create([
            'status' => 'published',
            'visibility' => 'authenticated',
        ]);
        $category = $this->form->categories()->create(['name' => 'General', 'order' => 1]);
        $field = $category->fields()->create([
            'form_id' => $this->form->id,
            'label' => 'Name',
            'type' => 'text',
            'required' => false,
            'order' => 1,
        ]);

        $this->submission = Submission::create([
            'form_id' => $this->form->id,
            'user_id' => $this->owner->id,
            'status' => 'draft',
        ]);
        $this->submission->values()->create([
            'form_field_id' => $field->id,
            'value' => 'Alice',
        ]);
    }

    public function test_submission_edit_page_renders(): void
    {
        $this->actingAs($this->owner)
            ->get(route('submissions.edit', [
                'form' => $this->form,
                'submission' => $this->submission,
            ]))
            ->assertOk();
    }

    public function test_submission_show_page_renders(): void
    {
        $this->actingAs($this->owner)
            ->get(route('submissions.show', [
                'form' => $this->form,
                'submission' => $this->submission,
            ]))
            ->assertOk();
    }

    public function test_submission_index_page_renders(): void
    {
        $this->actingAs($this->owner)
            ->get(route('submissions.index', ['form' => $this->form]))
            ->assertOk();
    }

    public function test_my_submissions_page_renders(): void
    {
        $this->actingAs($this->owner)
            ->get(route('submissions.user'))
            ->assertOk();
    }
}
