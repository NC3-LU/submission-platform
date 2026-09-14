<?php

namespace Tests\Feature;

use App\Jobs\ScanSubmissionFileJob;
use App\Models\Form;
use App\Models\ScanResult;
use App\Models\Submission;
use App\Models\SubmissionValues;
use App\Models\User;
use App\Services\FileScanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ScanVerdictDetailsTest extends TestCase
{
    use RefreshDatabase;

    private function attachment(): SubmissionValues
    {
        Storage::fake('private');
        $form = Form::factory()->create();
        $category = $form->categories()->create(['name' => 'Report', 'order' => 1]);
        $field = $category->fields()->create(['form_id' => $form->id, 'label' => 'Attachment', 'type' => 'file', 'order' => 1]);
        $submission = Submission::factory()->submitted()->create(['form_id' => $form->id]);
        $path = 'submissions/'.$submission->id.'/report.pdf';
        Storage::disk('private')->put($path, '%PDF-1.4 synthetic report');

        return $submission->values()->create(['form_field_id' => $field->id, 'value' => $path]);
    }

    private function scan(SubmissionValues $value, string $status, array $details = []): ScanResult
    {
        return ScanResult::create(['submission_id' => $value->submission_id, 'submission_value_id' => $value->id,
            'status' => $status, 'is_malicious' => $status === 'malicious', 'scanner_used' => 'pandora', 'filename' => 'report.pdf', 'scan_results' => $details]);
    }

    private function show(SubmissionValues $value): string
    {
        return route('submissions.show', ['form' => $value->submission->form_id, 'submission' => $value->submission_id]);
    }

    public function test_owner_and_evaluator_can_read_escaped_worker_details_without_seeds_or_report_links(): void
    {
        config(['services.pandora.enabled' => true, 'services.pandora.block_malicious' => false]);
        $value = $this->attachment();
        $form = $value->submission->form;
        $this->scan($value, 'malicious', ['status' => 'ALERT', 'seed' => 'private-scan-seed', 'link' => 'javascript:alert(1)',
            'workers' => ['clamav' => ['status' => 'ALERT', 'details' => ['signature' => 'Eicar-Test-Signature', 'message' => '<script>alert(1)</script>']]]]);
        $evaluator = User::factory()->create(['role' => 'external_evaluator']);
        $form->appointedUsers()->attach($evaluator, ['can_edit' => false]);
        foreach ([$form->user, $evaluator] as $viewer) {
            $this->actingAs($viewer)->get($this->show($value))->assertOk()
                ->assertSee('View scan details')->assertSee('clamav')->assertSee('Eicar-Test-Signature')
                ->assertSee('pandora')->assertSee('&lt;script&gt;', false)
                ->assertDontSee('<script>alert(1)</script>', false)
                ->assertDontSee('private-scan-seed')->assertDontSee('javascript:alert(1)', false)
                ->assertDontSee('Blocked — malware detected');
        }
    }

    public function test_details_are_null_safe_and_support_every_scan_state(): void
    {
        config(['services.pandora.enabled' => true]);
        $value = $this->attachment();
        $owner = $value->submission->form->user;
        $this->actingAs($owner)->get($this->show($value))->assertOk()->assertSee('Scan has not completed yet.');
        foreach (['pending', 'clean', 'malicious', 'failed'] as $status) {
            ScanResult::where('submission_value_id', $value->id)->delete();
            $this->scan($value, $status);
            $this->actingAs($owner)->get($this->show($value))->assertOk()->assertSee('View scan details');
        }
    }

    public function test_details_are_hidden_when_disabled_and_inaccessible_to_other_accounts(): void
    {
        $value = $this->attachment();
        $this->scan($value, 'clean', ['workers' => ['clamav' => ['status' => 'CLEAN', 'details' => ['signature' => 'private-verdict-fixture']]]]);
        config(['services.pandora.enabled' => false]);
        $this->actingAs($value->submission->form->user)->get($this->show($value))->assertOk()->assertDontSee('View scan details')->assertDontSee('private-verdict-fixture');
        config(['services.pandora.enabled' => true]);
        $this->actingAs(User::factory()->create())->get($this->show($value))->assertForbidden()->assertDontSee('private-verdict-fixture');
    }

    public function test_warning_mode_preserves_download_and_file_contents(): void
    {
        config(['services.pandora.enabled' => true, 'services.pandora.block_malicious' => false]);
        $value = $this->attachment();
        $this->scan($value, 'malicious', ['workers' => ['clamav' => ['status' => 'ALERT']]]);
        $this->actingAs($value->submission->form->user)->get($this->show($value))->assertOk()->assertSee('View scan details');
        $this->get(route('submissions.download', ['submission' => $value->submission_id, 'filename' => 'report.pdf']))->assertOk()->assertDownload();
        $this->assertSame('%PDF-1.4 synthetic report', Storage::disk('private')->get($value->value));
    }

    public function test_scanner_collects_and_persists_full_worker_verdicts(): void
    {
        config(['services.pandora.enabled' => true, 'services.pandora.block_malicious' => false, 'services.pandora.url' => 'http://pandora:6100']);
        Http::fake([
            '*/submit*' => Http::response(['success' => true, 'taskId' => 'task-49', 'seed' => 'private-seed']),
            '*/task_status*' => Http::response(['status' => 'ALERT', 'workersStatus' => ['clamav' => 'ALERT']]),
            '*/worker_status*' => Http::response(['clamav' => ['status' => 'ALERT', 'details' => ['signature' => 'Eicar-Test-Signature']]]),
        ]);
        $value = $this->attachment();
        (new ScanSubmissionFileJob($value))->handle(app(FileScanService::class));
        $this->assertSame('Eicar-Test-Signature', $value->scanResult->scan_results['workers']['clamav']['details']['signature'] ?? null);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/worker_status') && $request['seed'] === 'private-seed' && (int) $request['all_workers'] === 1 && (int) $request['details'] === 1);
        Storage::disk('private')->assertExists($value->value);
    }

    public function test_worker_detail_failure_does_not_change_the_completed_verdict(): void
    {
        config(['services.pandora.enabled' => true, 'services.pandora.block_malicious' => false, 'services.pandora.url' => 'http://pandora:6100']);
        Http::fake([
            '*/submit*' => Http::response(['success' => true, 'taskId' => 'task-49']),
            '*/task_status*' => Http::response(['status' => 'CLEAN']),
            '*/worker_status*' => Http::response('Unavailable', 503),
        ]);
        $value = $this->attachment();
        (new ScanSubmissionFileJob($value))->handle(app(FileScanService::class));
        $this->assertSame('clean', $value->scanResult->status);
        $this->assertFalse($value->scanResult->scan_results['report_details_available'] ?? true);
    }
}
