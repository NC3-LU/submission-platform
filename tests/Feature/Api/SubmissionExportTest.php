<?php

namespace Tests\Feature\Api;

use App\Jobs\GenerateSubmissionExport;
use App\Models\ApiToken;
use App\Models\Form;
use App\Models\Submission;
use App\Models\SubmissionExport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class SubmissionExportTest extends TestCase
{
    use RefreshDatabase;

    private function setupExport(int $rows = 1): Form
    {
        Storage::fake('private');
        Queue::fake();
        $form = Form::factory()->create();
        $category = $form->categories()->create(['name' => 'Answers', 'order' => 1]);
        $field = $category->fields()->create(['form_id' => $form->id, 'type' => 'text', 'label' => 'Answer', 'order' => 1]);
        for ($i = 0; $i < $rows; $i++) {
            $submission = Submission::factory()->submitted()->create(['form_id' => $form->id]);
            $submission->values()->create(['form_field_id' => $field->id, 'value' => $i === 0 ? '=HYPERLINK("https://example.invalid")' : "Answer $i"]);
        }
        Submission::factory()->create(['form_id' => $form->id, 'status' => 'draft']);
        $this->token($form->user);

        return $form;
    }

    private function token(User $user, array $abilities = ['submissions:export']): void
    {
        $secret = Str::random(48);
        ApiToken::create(['user_id' => $user->id, 'name' => 'Export', 'token' => hash('sha256', $secret), 'abilities' => $abilities]);
        $this->withToken($secret);
    }

    private function createExport(Form $form, string $format = 'json'): string
    {
        return $this->postJson("/api/v1/forms/{$form->id}/exports", ['format' => $format])->assertAccepted()->assertJsonPath('data.status', 'queued')->json('data.id');
    }

    public function test_request_only_enqueues_and_completed_json_is_private_scoped_and_minimal(): void
    {
        $form = $this->setupExport(123);
        $id = $this->createExport($form);
        Queue::assertPushed(GenerateSubmissionExport::class);
        $this->assertSame([], Storage::disk('private')->allFiles());
        $url = "/api/v1/forms/{$form->id}/exports/$id";
        $this->getJson($url.'/download')->assertStatus(409);
        (new GenerateSubmissionExport($id))->handle();
        $this->getJson($url)->assertOk()->assertJsonPath('data.status', 'completed')->assertJsonPath('data.row_count', 123)->assertJsonMissingPath('data.path');
        $response = $this->getJson($url.'/download')->assertOk()->assertDownload()->assertHeader('Cache-Control', 'no-store, private');
        $data = json_decode($response->streamedContent(), true);
        $this->assertCount(123, $data['submissions']);
        $this->assertArrayNotHasKey('ip_address', $data['submissions'][0]);
        $this->assertArrayNotHasKey('user', $data['submissions'][0]);
        foreach (['export.created', 'export.completed', 'export.downloaded'] as $action) {
            $this->assertDatabaseHas('integration_events', ['action' => $action, 'resource_id' => $id]);
        }
        $this->token(User::factory()->create(['role' => 'admin']));
        $this->getJson($url)->assertNotFound();
        $this->getJson($url.'/download')->assertNotFound();
    }

    public function test_xlsx_uses_literal_strings_instead_of_formulas(): void
    {
        $form = $this->setupExport();
        $id = $this->createExport($form, 'xlsx');
        (new GenerateSubmissionExport($id))->handle();
        $export = SubmissionExport::findOrFail($id);
        $this->assertSame('completed', $export->status);
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open(Storage::disk('private')->path($export->path)));
        $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        $this->assertStringContainsString('=HYPERLINK', $xml);
        $this->assertStringNotContainsString('<f>', $xml);
    }

    public function test_form_policy_ability_and_cross_form_scope_are_required(): void
    {
        $form = $this->setupExport();
        $id = $this->createExport($form);
        $other = Form::factory()->create(['user_id' => $form->user_id]);
        $this->getJson("/api/v1/forms/{$other->id}/exports/$id")->assertNotFound();
        $this->token($form->user, ['submissions:read']);
        $this->postJson("/api/v1/forms/{$form->id}/exports", ['format' => 'json'])->assertForbidden();
        $this->token(User::factory()->create());
        $this->postJson("/api/v1/forms/{$form->id}/exports", ['format' => 'json'])->assertNotFound();
    }

    public function test_concurrent_and_row_limits_are_enforced_before_queueing(): void
    {
        $form = $this->setupExport(3);
        config(['integrations.exports.max_rows' => 2]);
        $this->postJson("/api/v1/forms/{$form->id}/exports", ['format' => 'json'])->assertUnprocessable();
        Queue::assertNothingPushed();
        config(['integrations.exports.max_rows' => 10]);
        $this->createExport($form);
        $this->createExport($form);
        $this->postJson("/api/v1/forms/{$form->id}/exports", ['format' => 'json'])->assertStatus(429);
        $this->assertDatabaseCount('submission_exports', 2);
    }

    public function test_permissions_are_rechecked_when_job_runs_and_when_downloaded(): void
    {
        $form = $this->setupExport();
        $evaluator = User::factory()->create(['role' => 'external_evaluator']);
        $form->appointedUsers()->attach($evaluator, ['can_edit' => true]);
        $this->token($evaluator);
        $id = $this->createExport($form);
        $form->appointedUsers()->detach($evaluator);
        (new GenerateSubmissionExport($id))->handle();
        $this->assertDatabaseHas('submission_exports', ['id' => $id, 'status' => 'failed', 'error_code' => 'access_revoked']);
        $this->assertSame([], Storage::disk('private')->allFiles());
        $this->getJson("/api/v1/forms/{$form->id}/exports/$id/download")->assertNotFound();
    }

    public function test_byte_limit_failure_does_not_leave_artifacts_and_has_safe_error(): void
    {
        $form = $this->setupExport();
        $id = $this->createExport($form);
        config(['integrations.exports.max_bytes' => 50]);
        (new GenerateSubmissionExport($id))->handle();
        $this->getJson("/api/v1/forms/{$form->id}/exports/$id")->assertOk()->assertJsonPath('data.status', 'failed')->assertJsonPath('data.error_code', 'limit_exceeded')->assertJsonMissingPath('data.exception');
        $this->assertSame([], Storage::disk('private')->allFiles());
        $this->assertDatabaseHas('integration_events', ['action' => 'export.failed', 'resource_id' => $id]);
    }

    public function test_expiry_refuses_download_then_cleanup_removes_artifact_and_audits_once(): void
    {
        $form = $this->setupExport();
        $id = $this->createExport($form);
        (new GenerateSubmissionExport($id))->handle();
        $path = SubmissionExport::findOrFail($id)->path;
        $this->travel(25)->hours();
        $this->getJson("/api/v1/forms/{$form->id}/exports/$id/download")->assertStatus(410);
        $this->artisan('app:prune-integration-artifacts')->assertSuccessful();
        $this->artisan('app:prune-integration-artifacts')->assertSuccessful();
        Storage::disk('private')->assertMissing($path);
        $this->assertDatabaseHas('submission_exports', ['id' => $id, 'status' => 'expired', 'path' => null]);
        $this->assertSame(1, DB::table('integration_events')->where('action', 'export.deleted')->where('resource_id', $id)->count());
    }

    public function test_worker_failure_cleanup_removes_spreadsheet_temporary_files(): void
    {
        $form = $this->setupExport();
        $id = $this->createExport($form, 'xlsx');
        $export = SubmissionExport::findOrFail($id);
        $export->update(['status' => 'processing']);
        Storage::disk('private')->put("exports/$id/spout-worker-temp/sheet.xml", 'Synthetic partial export');
        $this->travel(31)->minutes();
        $this->artisan('app:prune-integration-artifacts')->assertSuccessful();
        $this->assertSame([], Storage::disk('private')->allFiles());
        $this->assertSame('failed', $export->fresh()->status);
    }

    public function test_duplicate_job_cannot_overwrite_completed_export(): void
    {
        $form = $this->setupExport();
        $id = $this->createExport($form);
        (new GenerateSubmissionExport($id))->handle();
        $path = SubmissionExport::findOrFail($id)->path;
        $bytes = Storage::disk('private')->get($path);
        (new GenerateSubmissionExport($id))->handle();
        $this->assertSame($bytes, Storage::disk('private')->get($path));
        $this->assertSame(1, DB::table('integration_events')->where('action', 'export.completed')->where('resource_id', $id)->count());
    }
}
