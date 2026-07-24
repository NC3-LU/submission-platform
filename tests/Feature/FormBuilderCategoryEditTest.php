<?php

namespace Tests\Feature;

use App\Livewire\FormFieldManager;
use App\Models\Form;
use App\Models\FormCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Editing a section goes editCategory() -> edit the modal -> updateCategory().
 * The modal only offers a name and a description, so those are the only values
 * updateCategory() may demand.
 *
 * It used to also require categoryBeingEdited.percentage_start/_end. Those keys
 * are never present: form_categories has no such columns, so editCategory()'s
 * toArray() cannot produce them and the modal has no inputs for them. Every
 * save failed validation, and because the modal renders @error only for name
 * and description, the failure was invisible — Save simply did nothing.
 */
class FormBuilderCategoryEditTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Form $form;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->form = Form::factory()->create(['user_id' => $this->user->id]);
    }

    private function category(string $name): FormCategory
    {
        return FormCategory::create([
            'form_id' => $this->form->id,
            'name' => $name,
            'description' => 'Original description',
            'order' => 1,
        ]);
    }

    private function builder()
    {
        return Livewire::actingAs($this->user)
            ->test(FormFieldManager::class, ['form' => $this->form]);
    }

    public function test_a_section_can_be_renamed_through_the_edit_modal(): void
    {
        $category = $this->category('Before');

        $this->builder()
            ->call('editCategory', $category->id)
            ->set('categoryBeingEdited.name', 'After')
            ->call('updateCategory')
            ->assertHasNoErrors();

        $this->assertSame('After', $category->fresh()->name);
    }

    public function test_a_section_description_can_be_edited_through_the_modal(): void
    {
        $category = $this->category('Section');

        $this->builder()
            ->call('editCategory', $category->id)
            ->set('categoryBeingEdited.description', 'Updated description')
            ->call('updateCategory')
            ->assertHasNoErrors();

        $this->assertSame('Updated description', $category->fresh()->description);
    }

    public function test_the_modal_closes_after_a_successful_save(): void
    {
        $category = $this->category('Section');

        $component = $this->builder()
            ->call('editCategory', $category->id)
            ->set('categoryBeingEdited.name', 'Renamed')
            ->call('updateCategory');

        $this->assertFalse($component->get('editingCategory'));
        $this->assertNull($component->get('categoryBeingEdited'));
    }

    public function test_a_section_still_requires_a_name(): void
    {
        $category = $this->category('Before');

        $this->builder()
            ->call('editCategory', $category->id)
            ->set('categoryBeingEdited.name', '')
            ->call('updateCategory')
            ->assertHasErrors(['categoryBeingEdited.name' => 'required']);

        $this->assertSame('Before', $category->fresh()->name);
    }

    public function test_a_new_section_can_be_added(): void
    {
        $this->builder()
            ->set('newCategory.name', 'Fresh section')
            ->call('addCategory')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('form_categories', [
            'form_id' => $this->form->id,
            'name' => 'Fresh section',
        ]);
    }

    public function test_a_new_section_still_requires_a_name(): void
    {
        $this->builder()
            ->set('newCategory.name', '')
            ->call('addCategory')
            ->assertHasErrors(['newCategory.name' => 'required']);
    }
}
