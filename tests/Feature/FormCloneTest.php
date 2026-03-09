<?php

namespace Tests\Feature;

use App\Models\Form;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormCloneTest extends TestCase
{
    use RefreshDatabase;

    public function test_form_owner_can_clone_form(): void
    {
        $user = User::factory()->create(['role' => 'internal_evaluator']);
        $form = Form::factory()->for($user)->create([
            'status' => 'published',
            'visibility' => 'public',
        ]);
        $category = $form->categories()->create(['name' => 'General', 'order' => 1]);
        $category->fields()->create([
            'form_id' => $form->id,
            'label' => 'Name',
            'type' => 'text',
            'required' => true,
            'order' => 1,
        ]);
        $category->fields()->create([
            'form_id' => $form->id,
            'label' => 'Email',
            'type' => 'text',
            'required' => false,
            'order' => 2,
        ]);

        $this->actingAs($user);

        $response = $this->post(route('forms.duplicate', $form));

        $response->assertRedirect();

        $this->assertDatabaseCount('forms', 2);
        $clone = Form::where('id', '!=', $form->id)->first();
        $this->assertEquals($form->title . ' (Copy)', $clone->title);
        $this->assertEquals('draft', $clone->status);
        $this->assertCount(1, $clone->categories);
        $this->assertCount(2, $clone->categories->first()->fields);
    }

    public function test_regular_user_cannot_clone_form(): void
    {
        $owner = User::factory()->create(['role' => 'internal_evaluator']);
        $user = User::factory()->create(['role' => 'user']);
        $form = Form::factory()->for($owner)->create();

        $this->actingAs($user);
        $this->withoutExceptionHandling();

        $this->expectException(AuthorizationException::class);

        $this->post(route('forms.duplicate', $form));
    }
}
