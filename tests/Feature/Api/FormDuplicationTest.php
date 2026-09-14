<?php

namespace Tests\Feature\Api;

use App\Models\ApiToken;
use App\Models\Form;
use App\Models\FormField;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class FormDuplicationTest extends TestCase
{
    use RefreshDatabase;

    private function token(User $user, array $abilities = ['forms:create']): void
    {
        $secret = Str::random(48);
        ApiToken::create(['user_id' => $user->id, 'name' => 'Duplicate', 'token' => hash('sha256', $secret), 'abilities' => $abilities]);
        $this->withToken($secret);
    }

    private function source(): Form
    {
        Storage::fake('public');
        $form = Form::factory()->create(['status' => 'published', 'header_image' => 'form-headers/original.png', 'header_theme_color' => '#123456', 'available_from' => now()]);
        Storage::disk('public')->put($form->header_image, 'Synthetic image');
        $category = $form->categories()->create(['name' => 'Général', 'description' => 'Détails', 'order' => 1]);
        $choice = $category->fields()->create(['form_id' => $form->id, 'type' => 'select', 'label' => 'Choix', 'options' => 'Oui,Non', 'required' => true, 'order' => 1]);
        $category->fields()->create(['form_id' => $form->id, 'type' => 'textarea', 'label' => 'Détails', 'content' => 'Explanation', 'char_limit' => 120, 'order' => 2, 'depends_on_field_id' => $choice->id, 'depends_on_value' => 'Oui']);
        $form->accessLinks()->create(['token' => Str::random(40), 'name' => 'Private link', 'created_by' => $form->user_id]);
        Submission::factory()->submitted()->create(['form_id' => $form->id]);

        return $form;
    }

    public function test_copy_is_independent_owned_draft_with_remapped_conditions_and_managed_asset(): void
    {
        $source = $this->source();
        $caller = User::factory()->create(['role' => 'internal_evaluator']);
        $source->appointedUsers()->attach($caller, ['can_edit' => true]);
        $this->token($caller);
        $response = $this->postJson("/api/v1/forms/{$source->id}/duplicate")->assertCreated()->assertJsonPath('data.status', 'draft')->assertJsonPath('data.user_id', $caller->id);
        $response->assertJsonPath('data.categories.0.description', 'Détails')->assertJsonPath('data.categories.0.fields.1.char_limit', 120);
        $copy = Form::findOrFail($response->json('data.id'));
        $this->assertNull($copy->available_from);
        $this->assertSame('#123456', $copy->header_theme_color);
        $this->assertSame('Général', $copy->categories->first()->name);
        $fields = $copy->fields()->orderBy('order')->get();
        $this->assertSame('Oui,Non', $fields[0]->options);
        $this->assertSame($fields[0]->id, $fields[1]->depends_on_field_id);
        $this->assertSame(120, $fields[1]->char_limit);
        $this->assertNotSame($source->header_image, $copy->header_image);
        $this->assertSame('Synthetic image', Storage::disk('public')->get($copy->header_image));
        $this->assertSame(0, $copy->submissions()->count());
        $this->assertSame(0, $copy->accessLinks()->count());
        $this->assertSame(0, $copy->appointedUsers()->count());
        $fields[0]->update(['label' => 'Changed']);
        $this->assertSame('Choix', $source->fields()->orderBy('order')->first()->label);
        $copy->delete();
        Storage::disk('public')->assertExists($source->header_image);
    }

    public function test_policy_and_ability_are_both_required(): void
    {
        $source = $this->source();
        $this->token($source->user, ['forms:read']);
        $this->postJson("/api/v1/forms/{$source->id}/duplicate")->assertForbidden();
        $this->token(User::factory()->create(['role' => 'internal_evaluator']));
        $this->postJson("/api/v1/forms/{$source->id}/duplicate")->assertNotFound();
        $this->assertDatabaseCount('forms', 1);
    }

    public function test_arbitrary_and_missing_assets_are_not_copied(): void
    {
        $source = $this->source();
        $this->token($source->user);
        Storage::disk('public')->put('sensitive.txt', 'private-data');
        foreach (['sensitive.txt', 'form-headers/../sensitive.txt', 'form-headers/missing.png'] as $path) {
            $source->update(['header_image' => $path]);
            $response = $this->postJson("/api/v1/forms/{$source->id}/duplicate")->assertCreated();
            $this->assertNull(Form::find($response->json('data.id'))->header_image);
        }
    }

    public function test_failure_rolls_back_every_row_and_copied_asset(): void
    {
        $source = $this->source();
        $this->token($source->user);
        FormField::creating(function ($field) use ($source) {
            if ($field->form_id !== $source->id) {
                throw new \RuntimeException('Synthetic copy failure');
            }
        });
        $this->postJson("/api/v1/forms/{$source->id}/duplicate")->assertStatus(500);
        $this->assertDatabaseCount('forms', 1);
        $this->assertDatabaseCount('form_categories', 1);
        $this->assertDatabaseCount('form_fields', 2);
        $this->assertSame(['form-headers/original.png'], Storage::disk('public')->allFiles());
    }
}
