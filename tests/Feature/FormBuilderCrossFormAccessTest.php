<?php

namespace Tests\Feature;

use App\Livewire\FormFieldManager;
use App\Models\Form;
use App\Models\FormCategory;
use App\Models\FormField;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * mount() authorizes the mounted form, but every action afterwards takes a
 * client-supplied id. Without scoping those lookups to the mounted form, an
 * owner of any form can drive the builder against someone else's categories
 * and fields. These tests pin that scoping.
 */
class FormBuilderCrossFormAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $attacker;

    private User $victim;

    private Form $attackerForm;

    private Form $victimForm;

    private FormCategory $victimCategory;

    private FormField $victimField;

    protected function setUp(): void
    {
        parent::setUp();

        $this->attacker = User::factory()->create();
        $this->victim = User::factory()->create();

        $this->attackerForm = Form::factory()->create(['user_id' => $this->attacker->id]);
        $this->victimForm = Form::factory()->create(['user_id' => $this->victim->id]);

        $this->victimCategory = FormCategory::create([
            'form_id' => $this->victimForm->id,
            'name' => 'Victim section',
            'order' => 1,
        ]);

        $this->victimField = FormField::create([
            'form_id' => $this->victimForm->id,
            'form_category_id' => $this->victimCategory->id,
            'type' => 'text',
            'label' => 'Victim field',
            'order' => 1,
        ]);
    }

    /**
     * The builder mounted on the attacker's own form.
     */
    private function builder(): Testable
    {
        return Livewire::actingAs($this->attacker)
            ->test(FormFieldManager::class, ['form' => $this->attackerForm]);
    }

    /**
     * Run a builder action that targets another form and assert it is rejected
     * rather than silently applied.
     */
    private function assertRejected(callable $action): void
    {
        try {
            $action();
        } catch (ModelNotFoundException $e) {
            $this->assertTrue(true, 'Action rejected as expected.');

            return;
        }

        $this->fail('Expected the cross-form action to be rejected.');
    }

    public function test_a_foreign_category_cannot_be_deleted(): void
    {
        $this->assertRejected(fn () => $this->builder()
            ->set('categoryToDelete', $this->victimCategory->id)
            ->call('deleteCategory'));

        $this->assertDatabaseHas('form_categories', ['id' => $this->victimCategory->id]);
        $this->assertDatabaseHas('form_fields', ['id' => $this->victimField->id]);
    }

    public function test_a_foreign_field_cannot_be_deleted(): void
    {
        $this->assertRejected(fn () => $this->builder()
            ->set('fieldToDelete', $this->victimField->id)
            ->call('deleteField'));

        $this->assertDatabaseHas('form_fields', ['id' => $this->victimField->id]);
    }

    public function test_a_foreign_category_cannot_be_loaded_for_editing(): void
    {
        $this->assertRejected(fn () => $this->builder()
            ->call('editCategory', $this->victimCategory->id));
    }

    public function test_a_foreign_field_cannot_be_loaded_for_editing(): void
    {
        $this->assertRejected(fn () => $this->builder()
            ->call('editField', $this->victimField->id));
    }

    public function test_a_foreign_category_cannot_be_updated(): void
    {
        $this->assertRejected(fn () => $this->builder()
            ->set('categoryBeingEdited', [
                'id' => $this->victimCategory->id,
                'form_id' => $this->victimForm->id,
                'name' => 'Owned',
                'description' => null,
            ])
            ->call('updateCategory'));

        $this->assertSame('Victim section', $this->victimCategory->fresh()->name);
    }

    public function test_a_foreign_field_cannot_be_updated(): void
    {
        $this->assertRejected(fn () => $this->builder()
            ->set('fieldBeingEdited', [
                'id' => $this->victimField->id,
                'form_id' => $this->victimForm->id,
                'form_category_id' => $this->victimCategory->id,
                'type' => 'text',
                'label' => 'Owned',
                'options' => '',
                'content' => '',
                'required' => false,
                'char_limit' => null,
                'depends_on_field_id' => null,
                'depends_on_value' => null,
            ])
            ->call('updateField'));

        $this->assertSame('Victim field', $this->victimField->fresh()->label);
    }

    public function test_updating_an_own_field_cannot_reparent_it_to_another_form(): void
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
            'label' => 'Mine',
            'order' => 1,
        ]);

        $this->builder()
            ->set('fieldBeingEdited', [
                'id' => $field->id,
                'form_id' => $this->victimForm->id,
                'form_category_id' => $this->victimCategory->id,
                'type' => 'text',
                'label' => 'Renamed',
                'options' => '',
                'content' => '',
                'required' => false,
                'char_limit' => null,
                'depends_on_field_id' => null,
                'depends_on_value' => null,
            ])
            ->call('updateField');

        $field->refresh();

        $this->assertSame($this->attackerForm->id, $field->form_id);
        $this->assertSame($category->id, $field->form_category_id);
    }

    public function test_updating_an_own_category_cannot_reparent_it_to_another_form(): void
    {
        $category = FormCategory::create([
            'form_id' => $this->attackerForm->id,
            'name' => 'Mine',
            'order' => 1,
        ]);

        $this->builder()
            ->set('categoryBeingEdited', [
                'id' => $category->id,
                'form_id' => $this->victimForm->id,
                'name' => 'Renamed',
                'description' => null,
            ])
            ->call('updateCategory');

        $this->assertSame($this->attackerForm->id, $category->fresh()->form_id);
    }

    public function test_a_foreign_field_cannot_be_duplicated(): void
    {
        $this->assertRejected(fn () => $this->builder()
            ->call('duplicateField', $this->victimField->id));

        $this->assertSame(1, FormField::where('form_id', $this->victimForm->id)->count());
    }

    public function test_a_foreign_category_cannot_be_duplicated(): void
    {
        $this->assertRejected(fn () => $this->builder()
            ->call('duplicateCategory', $this->victimCategory->id));

        $this->assertSame(1, FormCategory::where('form_id', $this->victimForm->id)->count());
    }

    public function test_a_foreign_field_cannot_be_moved_between_categories(): void
    {
        $mine = FormCategory::create([
            'form_id' => $this->attackerForm->id,
            'name' => 'Mine',
            'order' => 1,
        ]);

        $this->assertRejected(fn () => $this->builder()
            ->call('moveFieldToCategory', $this->victimField->id, $mine->id));

        $this->assertSame($this->victimCategory->id, $this->victimField->fresh()->form_category_id);
    }

    public function test_a_field_cannot_be_moved_into_a_foreign_category(): void
    {
        $mine = FormCategory::create([
            'form_id' => $this->attackerForm->id,
            'name' => 'Mine',
            'order' => 1,
        ]);

        $field = FormField::create([
            'form_id' => $this->attackerForm->id,
            'form_category_id' => $mine->id,
            'type' => 'text',
            'label' => 'Mine',
            'order' => 1,
        ]);

        $this->assertRejected(fn () => $this->builder()
            ->call('moveFieldToCategory', $field->id, $this->victimCategory->id));

        $this->assertSame($mine->id, $field->fresh()->form_category_id);
    }

    public function test_a_field_cannot_be_quick_added_to_a_foreign_category(): void
    {
        $this->assertRejected(fn () => $this->builder()
            ->call('quickAddField', 'text', $this->victimCategory->id));

        $this->assertSame(1, FormField::where('form_category_id', $this->victimCategory->id)->count());
    }

    public function test_a_foreign_category_cannot_be_reordered(): void
    {
        $second = FormCategory::create([
            'form_id' => $this->victimForm->id,
            'name' => 'Victim second',
            'order' => 2,
        ]);

        $this->assertRejected(fn () => $this->builder()
            ->call('moveCategoryUp', $second->id));

        $this->assertSame(2, $second->fresh()->order);
        $this->assertSame(1, $this->victimCategory->fresh()->order);
    }

    public function test_a_foreign_field_cannot_be_reordered(): void
    {
        $second = FormField::create([
            'form_id' => $this->victimForm->id,
            'form_category_id' => $this->victimCategory->id,
            'type' => 'text',
            'label' => 'Victim second',
            'order' => 2,
        ]);

        $this->assertRejected(fn () => $this->builder()
            ->call('moveFieldUp', $second->id));

        $this->assertSame(2, $second->fresh()->order);
        $this->assertSame(1, $this->victimField->fresh()->order);
    }
}
