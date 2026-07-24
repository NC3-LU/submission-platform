<?php

namespace Tests\Feature;

use App\Livewire\SubmissionForm;
use App\Models\ApiToken;
use App\Models\Form;
use App\Models\FormCategory;
use App\Models\FormField;
use App\Models\SubmissionValues;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Submitted answers are keyed by field id, and those keys come from the client
 * on both the API and the Livewire path. A submission must only ever record
 * values for fields that belong to the form being submitted.
 */
class SubmissionFieldScopingTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Form $form;

    private FormField $ownField;

    private FormField $foreignField;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();

        $this->form = Form::factory()->published()->public()->create(['user_id' => $this->owner->id]);
        $this->ownField = $this->fieldOn($this->form, 'Own field');

        $otherForm = Form::factory()->published()->create(['user_id' => User::factory()->create()->id]);
        $this->foreignField = $this->fieldOn($otherForm, 'Foreign field');
    }

    private function fieldOn(Form $form, string $label): FormField
    {
        $category = FormCategory::create([
            'form_id' => $form->id,
            'name' => 'Section',
            'order' => 1,
        ]);

        return FormField::create([
            'form_id' => $form->id,
            'form_category_id' => $category->id,
            'type' => 'text',
            'label' => $label,
            'order' => 1,
        ]);
    }

    private function apiToken(): string
    {
        $plainText = Str::random(40);

        ApiToken::create([
            'user_id' => $this->owner->id,
            'name' => 'Submitting token',
            'token' => hash('sha256', $plainText),
            'abilities' => ['submissions:create'],
            'expires_at' => now()->addDay(),
        ]);

        return $plainText;
    }

    public function test_the_api_ignores_values_for_fields_of_another_form(): void
    {
        $this->withHeader('Authorization', 'Bearer '.$this->apiToken())
            ->postJson("/api/v1/forms/{$this->form->id}/submissions", [
                'values' => [
                    $this->ownField->id => 'legitimate',
                    $this->foreignField->id => 'injected',
                ],
            ])
            ->assertStatus(201);

        $this->assertDatabaseMissing('submission_values', [
            'form_field_id' => $this->foreignField->id,
        ]);

        $this->assertDatabaseHas('submission_values', [
            'form_field_id' => $this->ownField->id,
            'value' => 'legitimate',
        ]);
    }

    public function test_the_submission_form_ignores_values_for_fields_of_another_form(): void
    {
        Livewire::test(SubmissionForm::class, ['form' => $this->form])
            ->set("fieldValues.{$this->ownField->id}", 'legitimate')
            ->set("fieldValues.{$this->foreignField->id}", 'injected')
            ->call('submit');

        $this->assertSame(0, SubmissionValues::where('form_field_id', $this->foreignField->id)->count());
    }

    public function test_an_unpublished_form_cannot_be_submitted(): void
    {
        $draft = Form::factory()->draft()->public()->create(['user_id' => $this->owner->id]);
        $field = $this->fieldOn($draft, 'Draft field');

        Livewire::test(SubmissionForm::class, ['form' => $draft])
            ->set("fieldValues.{$field->id}", 'sneaked in')
            ->call('submit');

        $this->assertDatabaseMissing('submissions', ['form_id' => $draft->id, 'status' => 'submitted']);
    }
}
