<?php

namespace Tests\Feature;

use App\Models\Form;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The workflow feature was never finished: its controller, Livewire component
 * and Form relations referenced App\Models\Workflow and App\Models\WorkflowStep,
 * neither of which exists, and no migration ever created the backing tables.
 * Every one of its routes therefore raised a fatal error instead of answering.
 */
class WorkflowRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_workflow_management_route_does_not_fatal(): void
    {
        $user = User::factory()->create();
        $form = Form::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->get("/forms/{$form->id}/workflows/manage")
            ->assertNotFound();
    }

    public function test_the_workflow_show_route_does_not_fatal(): void
    {
        $user = User::factory()->create();
        $form = Form::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->get("/forms/{$form->id}/workflows/1")
            ->assertNotFound();
    }

    public function test_the_workflow_delete_routes_do_not_fatal(): void
    {
        $user = User::factory()->create();
        $form = Form::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->delete("/forms/{$form->id}/workflows/1")
            ->assertNotFound();

        $this->actingAs($user)
            ->delete("/forms/{$form->id}/workflows/steps/1")
            ->assertNotFound();
    }
}
