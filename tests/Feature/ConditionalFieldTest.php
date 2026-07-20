<?php

namespace Tests\Feature;

use App\Models\Form;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConditionalFieldTest extends TestCase
{
    use RefreshDatabase;

    public function test_conditional_field_columns_exist(): void
    {
        $user = User::factory()->create(['role' => 'internal_evaluator']);
        $form = Form::factory()->for($user)->create([
            'status' => 'published',
            'visibility' => 'public',
        ]);
        $category = $form->categories()->create(['name' => 'General', 'order' => 1]);

        $parentField = $category->fields()->create([
            'form_id' => $form->id,
            'label' => 'Do you agree?',
            'type' => 'radio',
            'options' => 'Yes,No',
            'required' => true,
            'order' => 1,
        ]);

        $conditionalField = $category->fields()->create([
            'form_id' => $form->id,
            'label' => 'Why do you agree?',
            'type' => 'textarea',
            'required' => false,
            'order' => 2,
            'depends_on_field_id' => $parentField->id,
            'depends_on_value' => 'Yes',
        ]);

        $this->assertDatabaseHas('form_fields', [
            'id' => $conditionalField->id,
            'depends_on_field_id' => $parentField->id,
            'depends_on_value' => 'Yes',
        ]);

        $conditionalField->refresh();
        $this->assertEquals($parentField->id, $conditionalField->dependsOnField->id);
    }
}
