<?php

namespace Tests\Feature;

use App\Models\Form;
use App\Models\FormCategory;
use App\Models\FormField;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `can:update,form` authorizes only the {form} segment. Without binding the
 * {field} segment to that form, an owner of any form can edit or delete fields
 * belonging to someone else's form.
 */
class FormFieldRouteScopingTest extends TestCase
{
    use RefreshDatabase;

    private User $attacker;

    private Form $attackerForm;

    private FormField $victimField;

    protected function setUp(): void
    {
        parent::setUp();

        $this->attacker = User::factory()->create();
        $victim = User::factory()->create();

        $this->attackerForm = Form::factory()->create(['user_id' => $this->attacker->id]);
        $victimForm = Form::factory()->create(['user_id' => $victim->id]);

        $victimCategory = FormCategory::create([
            'form_id' => $victimForm->id,
            'name' => 'Victim section',
            'order' => 1,
        ]);

        $this->victimField = FormField::create([
            'form_id' => $victimForm->id,
            'form_category_id' => $victimCategory->id,
            'type' => 'text',
            'label' => 'Victim field',
            'order' => 1,
        ]);
    }

    public function test_a_field_from_another_form_cannot_be_updated(): void
    {
        $category = FormCategory::create([
            'form_id' => $this->attackerForm->id,
            'name' => 'Mine',
            'order' => 1,
        ]);

        $this->actingAs($this->attacker)
            ->put(route('forms.fields.update', [$this->attackerForm, $this->victimField]), [
                'type' => 'text',
                'label' => 'Owned',
                'required' => false,
                'form_category_id' => $category->id,
            ])
            ->assertNotFound();

        $this->assertSame('Victim field', $this->victimField->fresh()->label);
    }

    public function test_a_field_from_another_form_cannot_be_deleted(): void
    {
        $this->actingAs($this->attacker)
            ->delete(route('forms.fields.destroy', [$this->attackerForm, $this->victimField]))
            ->assertNotFound();

        $this->assertDatabaseHas('form_fields', ['id' => $this->victimField->id]);
    }

    public function test_an_own_field_can_still_be_updated(): void
    {
        $category = FormCategory::create([
            'form_id' => $this->attackerForm->id,
            'name' => 'Mine',
            'order' => 1,
        ]);

        $field = FormField::create([
            'form_id' => $this->attackerForm->id,
            'form_category_id' => $category->id,
            'type' => 'text',
            'label' => 'Before',
            'order' => 1,
        ]);

        $this->actingAs($this->attacker)
            ->put(route('forms.fields.update', [$this->attackerForm, $field]), [
                'type' => 'text',
                'label' => 'After',
                'required' => false,
                'form_category_id' => $category->id,
            ])
            ->assertRedirect(route('forms.edit', $this->attackerForm));

        $this->assertSame('After', $field->fresh()->label);
    }

    public function test_an_own_field_can_still_be_deleted(): void
    {
        $category = FormCategory::create([
            'form_id' => $this->attackerForm->id,
            'name' => 'Mine',
            'order' => 1,
        ]);

        $field = FormField::create([
            'form_id' => $this->attackerForm->id,
            'form_category_id' => $category->id,
            'type' => 'text',
            'label' => 'Doomed',
            'order' => 1,
        ]);

        $this->actingAs($this->attacker)
            ->delete(route('forms.fields.destroy', [$this->attackerForm, $field]))
            ->assertRedirect(route('forms.edit', $this->attackerForm));

        $this->assertDatabaseMissing('form_fields', ['id' => $field->id]);
    }
}
