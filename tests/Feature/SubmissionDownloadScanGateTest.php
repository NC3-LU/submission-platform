<?php

namespace Tests\Feature;

use App\Models\Form;
use App\Models\FormCategory;
use App\Models\FormField;
use App\Models\ScanResult;
use App\Models\Submission;
use App\Models\SubmissionValues;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SubmissionDownloadScanGateTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Submission $submission;

    protected SubmissionValues $value;

    protected string $filename = 'doc.pdf';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
        config([
            'services.pandora.enabled' => true,
            'services.pandora.block_malicious' => true,
        ]);

        $this->owner = User::factory()->create();
        $form = Form::factory()->create(['user_id' => $this->owner->id]);
        $category = FormCategory::create(['form_id' => $form->id, 'name' => 'Files', 'order' => 1]);
        $field = FormField::create([
            'form_id' => $form->id,
            'form_category_id' => $category->id,
            'type' => 'file',
            'order' => 1,
            'label' => 'Attachment',
        ]);
        $this->submission = Submission::factory()->create([
            'form_id' => $form->id,
            'user_id' => $this->owner->id,
            'status' => 'submitted',
        ]);

        $path = "submissions/{$this->submission->id}/{$this->filename}";
        Storage::disk('private')->put($path, 'data');

        $this->value = SubmissionValues::create([
            'submission_id' => $this->submission->id,
            'form_field_id' => $field->id,
            'value' => $path,
        ]);
    }

    protected function makeScan(string $status): void
    {
        ScanResult::create([
            'submission_id' => $this->submission->id,
            'submission_value_id' => $this->value->id,
            'is_malicious' => $status === ScanResult::STATUS_MALICIOUS,
            'status' => $status,
            'scanner_used' => 'pandora',
            'filename' => $this->filename,
        ]);
    }

    protected function download()
    {
        return $this->actingAs($this->owner)
            ->get(route('submissions.download', [$this->submission, $this->filename]));
    }

    public function test_clean_file_is_downloadable(): void
    {
        $this->makeScan(ScanResult::STATUS_CLEAN);
        $this->download()->assertOk();
    }

    public function test_pending_file_is_blocked(): void
    {
        // No scan result yet — scan still queued.
        $this->download()->assertStatus(423);
    }

    public function test_failed_scan_file_is_blocked(): void
    {
        $this->makeScan(ScanResult::STATUS_FAILED);
        $this->download()->assertStatus(423);
    }

    public function test_gating_skipped_when_block_malicious_disabled(): void
    {
        config(['services.pandora.block_malicious' => false]);
        // No scan result, but gating is off, so the file is served.
        $this->download()->assertOk();
    }
}
