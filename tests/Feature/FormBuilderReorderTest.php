<?php

namespace Tests\Feature;

use App\Livewire\FormFieldManager;
use App\Models\Form;
use App\Models\FormCategory;
use App\Models\FormField;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The livewire-sortable plugin does not post plain ids. `wire:sortable` posts
 * [{order, value}] and `wire:sortable-group` posts
 * [{order, value, items: [{order, value}]}]. These tests pin those payloads.
 */
class FormBuilderReorderTest extends TestCase
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

    private function category(string $name, int $order): FormCategory
    {
        return FormCategory::create([
            'form_id' => $this->form->id,
            'name' => $name,
            'order' => $order,
        ]);
    }

    private function field(FormCategory $category, string $label, int $order): FormField
    {
        return FormField::create([
            'form_id' => $this->form->id,
            'form_category_id' => $category->id,
            'type' => 'text',
            'label' => $label,
            'order' => $order,
        ]);
    }

    public function test_dragging_a_section_reorders_it(): void
    {
        $first = $this->category('First', 1);
        $second = $this->category('Second', 2);
        $third = $this->category('Third', 3);

        // Second dragged to the top, as the browser posts it.
        Livewire::actingAs($this->user)
            ->test(FormFieldManager::class, ['form' => $this->form])
            ->call('updateCategoryOrder', [
                ['order' => 1, 'value' => (string) $second->id],
                ['order' => 2, 'value' => (string) $first->id],
                ['order' => 3, 'value' => (string) $third->id],
            ]);

        $this->assertSame(1, $second->fresh()->order);
        $this->assertSame(2, $first->fresh()->order);
        $this->assertSame(3, $third->fresh()->order);
    }

    public function test_section_order_is_reflected_in_the_component_state(): void
    {
        $first = $this->category('First', 1);
        $second = $this->category('Second', 2);

        $component = Livewire::actingAs($this->user)
            ->test(FormFieldManager::class, ['form' => $this->form])
            ->call('updateCategoryOrder', [
                ['order' => 1, 'value' => (string) $second->id],
                ['order' => 2, 'value' => (string) $first->id],
            ]);

        $names = array_column($component->get('categories'), 'name');

        $this->assertSame(['Second', 'First'], $names);
    }

    public function test_dragging_a_field_reorders_it_within_its_section(): void
    {
        $category = $this->category('Section', 1);
        $a = $this->field($category, 'A', 1);
        $b = $this->field($category, 'B', 2);

        Livewire::actingAs($this->user)
            ->test(FormFieldManager::class, ['form' => $this->form])
            ->call('updateFieldOrder', [
                ['order' => 1, 'value' => (string) $category->id, 'items' => [
                    ['order' => 1, 'value' => (string) $b->id],
                    ['order' => 2, 'value' => (string) $a->id],
                ]],
            ]);

        $this->assertSame(1, $b->fresh()->order);
        $this->assertSame(2, $a->fresh()->order);
        $this->assertSame($category->id, $b->fresh()->form_category_id);
    }

    public function test_dragging_a_field_into_another_section_moves_it(): void
    {
        $source = $this->category('Source', 1);
        $target = $this->category('Target', 2);
        $moved = $this->field($source, 'Moved', 1);
        $stays = $this->field($target, 'Stays', 1);

        Livewire::actingAs($this->user)
            ->test(FormFieldManager::class, ['form' => $this->form])
            ->call('updateFieldOrder', [
                ['order' => 1, 'value' => (string) $source->id, 'items' => []],
                ['order' => 2, 'value' => (string) $target->id, 'items' => [
                    ['order' => 1, 'value' => (string) $stays->id],
                    ['order' => 2, 'value' => (string) $moved->id],
                ]],
            ]);

        $this->assertSame($target->id, $moved->fresh()->form_category_id);
        $this->assertSame(2, $moved->fresh()->order);
        $this->assertSame(1, $stays->fresh()->order);
    }

    public function test_reordering_only_touches_the_current_form(): void
    {
        $otherForm = Form::factory()->create(['user_id' => $this->user->id]);
        $foreign = FormCategory::create([
            'form_id' => $otherForm->id,
            'name' => 'Foreign',
            'order' => 5,
        ]);

        $mine = $this->category('Mine', 1);

        Livewire::actingAs($this->user)
            ->test(FormFieldManager::class, ['form' => $this->form])
            ->call('updateCategoryOrder', [
                ['order' => 1, 'value' => (string) $foreign->id],
                ['order' => 2, 'value' => (string) $mine->id],
            ]);

        $this->assertSame(5, $foreign->fresh()->order);
    }
}
