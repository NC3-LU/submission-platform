<?php

namespace Tests\Feature\Api;

use App\Models\ApiToken;
use App\Models\Form;
use App\Models\IntegrationEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class FormCollaboratorTest extends TestCase
{
    use RefreshDatabase;

    private function token(User $user, array $abilities = ['forms:share']): void
    {
        $secret = Str::random(48);
        ApiToken::create(['user_id' => $user->id, 'name' => 'Share', 'token' => hash('sha256', $secret), 'abilities' => $abilities]);
        $this->withToken($secret);
    }

    public function test_owner_can_grant_update_list_and_revoke_with_idempotent_audits(): void
    {
        $form = Form::factory()->create(['visibility' => 'private']);
        $evaluator = User::factory()->create(['role' => 'external_evaluator']);
        $base = "/api/v1/forms/{$form->id}/users";
        $this->token($form->user);
        $this->postJson($base, ['user_id' => $evaluator->id, 'permission' => 'viewer'])->assertCreated()->assertJsonPath('data.permission', 'viewer');
        $this->postJson($base, ['user_id' => $evaluator->id, 'permission' => 'viewer'])->assertOk();
        $this->getJson($base)->assertOk()->assertJsonCount(1, 'data')->assertJsonMissingPath('data.0.email')->assertJsonMissingPath('data.0.password');
        $this->putJson($base.'/'.$evaluator->id, ['permission' => 'editor'])->assertOk()->assertJsonPath('data.permission', 'editor');
        $this->assertTrue($form->canAccess($evaluator));
        $this->deleteJson($base.'/'.$evaluator->id)->assertNoContent();
        $this->assertFalse($form->fresh()->canAccess($evaluator));
        foreach (['collaborator.granted', 'collaborator.updated', 'collaborator.removed'] as $action) {
            $this->assertSame(1, DB::table('integration_events')->where('action', $action)->where('form_id', $form->id)->count());
        }
    }

    public function test_editors_cannot_escalate_or_manage_sharing_and_ability_is_required(): void
    {
        $form = Form::factory()->create();
        $editor = User::factory()->create(['role' => 'internal_evaluator']);
        $form->appointedUsers()->attach($editor, ['can_edit' => true]);
        $this->token($editor);
        $this->getJson("/api/v1/forms/{$form->id}/users")->assertNotFound();
        $this->putJson("/api/v1/forms/{$form->id}/users/{$editor->id}", ['permission' => 'editor'])->assertNotFound();
        $this->token($form->user, ['forms:update']);
        $this->getJson("/api/v1/forms/{$form->id}/users")->assertForbidden();
    }

    public function test_owner_and_self_changes_are_rejected_and_permissions_are_closed(): void
    {
        $form = Form::factory()->create();
        $this->token($form->user);
        $base = "/api/v1/forms/{$form->id}/users";
        $this->postJson($base, ['user_id' => $form->user_id, 'permission' => 'viewer'])->assertUnprocessable();
        $this->deleteJson($base.'/'.$form->user_id)->assertUnprocessable();
        $this->postJson($base, ['user_id' => $form->user_id, 'permission' => 'admin'])->assertUnprocessable();
        $this->assertSame($form->user_id, $form->fresh()->user_id);
    }

    public function test_unrelated_accounts_and_missing_ids_have_identical_errors_and_children_are_scoped(): void
    {
        $form = Form::factory()->create();
        $this->token($form->user);
        $base = "/api/v1/forms/{$form->id}/users";
        $unrelated = User::factory()->create(['role' => 'user']);
        $actual = $this->postJson($base, ['user_id' => $unrelated->id, 'permission' => 'viewer'])->assertUnprocessable()->json();
        $missing = $this->postJson($base, ['user_id' => 999999, 'permission' => 'viewer'])->assertUnprocessable()->json();
        $this->assertSame($actual, $missing);
        $evaluator = User::factory()->create(['role' => 'external_evaluator']);
        Form::factory()->create()->appointedUsers()->attach($evaluator, ['can_edit' => false]);
        $this->putJson($base.'/'.$evaluator->id, ['permission' => 'editor'])->assertNotFound();
        $this->deleteJson($base.'/'.$evaluator->id)->assertNotFound();
    }

    public function test_audit_failure_rolls_back_grant(): void
    {
        $form = Form::factory()->create();
        $this->token($form->user);
        $evaluator = User::factory()->create(['role' => 'external_evaluator']);
        IntegrationEvent::creating(fn () => throw new \RuntimeException('Synthetic audit failure'));
        $this->postJson("/api/v1/forms/{$form->id}/users", ['user_id' => $evaluator->id, 'permission' => 'viewer'])->assertStatus(500);
        $this->assertSame(0, $form->appointedUsers()->count());
    }

    public function test_share_ability_can_be_delegated_only_when_caller_has_it(): void
    {
        $owner = User::factory()->create();
        $this->token($owner, ['tokens:manage', 'forms:share']);
        $this->postJson('/api/v1/tokens', ['name' => 'Sharing', 'abilities' => ['forms:share']])->assertCreated();
        $this->token($owner, ['tokens:manage']);
        $this->postJson('/api/v1/tokens', ['name' => 'Escalation', 'abilities' => ['forms:share']])->assertUnprocessable();
    }
}
