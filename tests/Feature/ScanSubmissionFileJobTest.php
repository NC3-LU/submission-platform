<?php

namespace Tests\Feature;

use App\Jobs\ScanSubmissionFileJob;
use App\Models\Form;
use App\Models\FormCategory;
use App\Models\FormField;
use App\Models\ScanResult;
use App\Models\Submission;
use App\Models\SubmissionValues;
use App\Notifications\SubmissionFileScanAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ScanSubmissionFileJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
        Notification::fake();
        Cache::flush();
        config([
            'services.pandora.enabled' => true,
            'services.pandora.url' => 'http://pandora:6100',
            'services.pandora.timeout' => 5,
            'services.pandora.poll_interval' => 0,
            'services.pandora.block_malicious' => true,
        ]);
    }

    /**
     * Create a file-type submission value with a real file stored on the private disk.
     */
    protected function makeFileValue(string $path = 'submissions/X/doc.pdf'): SubmissionValues
    {
        $form = Form::factory()->create();
        $category = FormCategory::create(['form_id' => $form->id, 'name' => 'Files', 'order' => 1]);
        $field = FormField::create([
            'form_id' => $form->id,
            'form_category_id' => $category->id,
            'type' => 'file',
            'order' => 1,
            'label' => 'Attachment',
        ]);
        $submission = Submission::factory()->create(['form_id' => $form->id, 'status' => 'submitted']);

        Storage::disk('private')->put($path, 'file contents');

        return SubmissionValues::create([
            'submission_id' => $submission->id,
            'form_field_id' => $field->id,
            'value' => $path,
        ]);
    }

    public function test_clean_file_records_clean_status_and_keeps_file(): void
    {
        Http::fake([
            '*/submit*' => Http::response(['success' => true, 'taskId' => 't1', 'seed' => 's1']),
            '*/task_status*' => Http::response(['success' => true, 'taskId' => 't1', 'status' => 'CLEAN']),
        ]);

        $value = $this->makeFileValue();
        (new ScanSubmissionFileJob($value))->handle(app(\App\Services\FileScanService::class));

        $scan = ScanResult::where('submission_value_id', $value->id)->first();
        $this->assertNotNull($scan);
        $this->assertSame(ScanResult::STATUS_CLEAN, $scan->status);
        $this->assertFalse($scan->is_malicious);
        $this->assertTrue(Storage::disk('private')->exists($value->value));
        $this->assertSame('submissions/X/doc.pdf', $value->fresh()->value);
    }

    public function test_malicious_file_is_quarantined_when_blocking_enabled(): void
    {
        Http::fake([
            '*/submit*' => Http::response(['success' => true, 'taskId' => 't2', 'seed' => 's2']),
            '*/task_status*' => Http::response(['success' => true, 'taskId' => 't2', 'status' => 'ALERT']),
        ]);

        $value = $this->makeFileValue('submissions/Y/malware.pdf');
        (new ScanSubmissionFileJob($value))->handle(app(\App\Services\FileScanService::class));

        $scan = ScanResult::where('submission_value_id', $value->id)->first();
        $this->assertSame(ScanResult::STATUS_MALICIOUS, $scan->status);
        $this->assertTrue($scan->is_malicious);
        // File removed, value marked as quarantined.
        $this->assertFalse(Storage::disk('private')->exists('submissions/Y/malware.pdf'));
        $this->assertStringContainsString('[REMOVED-MALICIOUS]', $value->fresh()->value);
    }

    public function test_failed_scan_throws_to_trigger_retry(): void
    {
        Http::fake(['*/submit*' => Http::response('boom', 500)]);

        $value = $this->makeFileValue();

        $this->expectException(\RuntimeException::class);
        (new ScanSubmissionFileJob($value))->handle(app(\App\Services\FileScanService::class));
    }

    public function test_failed_handler_records_failed_status(): void
    {
        $value = $this->makeFileValue();

        (new ScanSubmissionFileJob($value))->failed(new \RuntimeException('scan exhausted'));

        $scan = ScanResult::where('submission_value_id', $value->id)->first();
        $this->assertNotNull($scan);
        $this->assertSame(ScanResult::STATUS_FAILED, $scan->status);
        // The original file is preserved (we do not destroy user data on scanner outage).
        $this->assertTrue(Storage::disk('private')->exists($value->value));
    }

    public function test_quarantine_notifies_the_form_owner(): void
    {
        Http::fake([
            '*/submit*' => Http::response(['success' => true, 'taskId' => 't3', 'seed' => 's3']),
            '*/task_status*' => Http::response(['success' => true, 'taskId' => 't3', 'status' => 'ALERT']),
        ]);

        $value = $this->makeFileValue('submissions/Z/malware.pdf');
        $owner = $value->submission->form->user;
        $this->assertNotNull($owner);

        (new ScanSubmissionFileJob($value))->handle(app(\App\Services\FileScanService::class));

        Notification::assertSentTo(
            $owner,
            SubmissionFileScanAlert::class,
            fn (SubmissionFileScanAlert $notification) => $notification->reason === 'quarantined'
                && $notification->filename === 'malware.pdf'
        );
    }

    public function test_failed_handler_notifies_the_form_owner(): void
    {
        $value = $this->makeFileValue();
        $owner = $value->submission->form->user;
        $this->assertNotNull($owner);

        (new ScanSubmissionFileJob($value))->failed(new \RuntimeException('scan exhausted'));

        Notification::assertSentTo(
            $owner,
            SubmissionFileScanAlert::class,
            fn (SubmissionFileScanAlert $notification) => $notification->reason === 'scan_failed'
        );
    }

    public function test_repeated_scan_failures_for_same_form_notify_owner_once(): void
    {
        // During a scanner outage many files in a submission can fail at once.
        // The owner should get a single alert, not one email per file.
        $form = Form::factory()->create();
        $category = FormCategory::create(['form_id' => $form->id, 'name' => 'Files', 'order' => 1]);
        $field = FormField::create([
            'form_id' => $form->id,
            'form_category_id' => $category->id,
            'type' => 'file',
            'order' => 1,
            'label' => 'Attachment',
        ]);
        $submission = Submission::factory()->create(['form_id' => $form->id, 'status' => 'submitted']);

        $values = collect(['one.pdf', 'two.pdf', 'three.pdf'])->map(function ($name) use ($submission, $field) {
            $path = "submissions/{$submission->id}/{$name}";
            Storage::disk('private')->put($path, 'data');

            return SubmissionValues::create([
                'submission_id' => $submission->id,
                'form_field_id' => $field->id,
                'value' => $path,
            ]);
        });

        foreach ($values as $value) {
            (new ScanSubmissionFileJob($value))->failed(new \RuntimeException('scan exhausted'));
        }

        Notification::assertSentToTimes($form->user, SubmissionFileScanAlert::class, 1);
    }
}
