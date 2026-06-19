<?php

namespace App\Jobs;

use App\Models\ScanResult;
use App\Models\SubmissionValues;
use App\Notifications\SubmissionFileScanAlert;
use App\Services\FileScanService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\UploadedFile;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Scans a single submission file value via Pandora, asynchronously, after the
 * submission has been committed. Keeps the malware scan off the request path.
 *
 * Failure handling is fail-closed: a malicious file is quarantined immediately,
 * and a file that cannot be scanned (after retries) is recorded as `failed` so
 * download gating refuses to serve it while PANDORA_BLOCK_MALICIOUS is on.
 */
class ScanSubmissionFileJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Number of attempts before the job is considered permanently failed.
     */
    public int $tries = 3;

    /**
     * Seconds to wait between retries.
     *
     * @var array<int, int>
     */
    public array $backoff = [10, 30];

    /**
     * Cooldown (seconds) for "scan failed" owner alerts, so a scanner outage
     * affecting many files in one form collapses into a single notification.
     */
    public const FAILURE_NOTIFY_COOLDOWN = 900;

    public function __construct(public SubmissionValues $submissionValue) {}

    public function handle(FileScanService $scanService): void
    {
        $value = $this->submissionValue->fresh();

        if (! $value) {
            return;
        }

        $path = $value->value;

        // Nothing to scan if the file is missing, empty, or already quarantined.
        if (empty($path) || str_starts_with($path, '[REMOVED')) {
            return;
        }

        if (! Storage::disk('private')->exists($path)) {
            Log::warning('ScanSubmissionFileJob: file no longer exists', [
                'submission_value_id' => $value->id,
                'path' => $path,
            ]);

            return;
        }

        $filename = basename($path);
        $fullPath = Storage::disk('private')->path($path);
        $mimeType = Storage::disk('private')->mimeType($path);

        $uploadedFile = new UploadedFile($fullPath, $filename, $mimeType, null, true);
        $result = $scanService->scanFile($uploadedFile);

        if (! $result['success']) {
            // Throw so the queue retries with backoff; failed() records the
            // terminal `failed` state once attempts are exhausted.
            throw new RuntimeException('Pandora scan failed: '.($result['message'] ?? 'unknown error'));
        }

        $isMalicious = (bool) $result['is_malicious'];

        ScanResult::updateOrCreate(
            ['submission_value_id' => $value->id],
            [
                'submission_id' => $value->submission_id,
                'is_malicious' => $isMalicious,
                'status' => $isMalicious ? ScanResult::STATUS_MALICIOUS : ScanResult::STATUS_CLEAN,
                'scan_results' => $result['scan_results'],
                'scanner_used' => 'pandora',
                'filename' => $filename,
            ]
        );

        if ($isMalicious && config('services.pandora.block_malicious', true)) {
            Log::warning('ScanSubmissionFileJob: malicious file detected, quarantining', [
                'submission_value_id' => $value->id,
                'filename' => $filename,
            ]);

            Storage::disk('private')->delete($path);
            $value->update(['value' => '[REMOVED-MALICIOUS]: '.$filename]);

            $this->notifyOwner($value, $filename, 'quarantined');
        }
    }

    /**
     * Notify the form owner about a quarantined or unscannable file. Skips
     * silently if the form has no owner so the job never crashes.
     */
    protected function notifyOwner(SubmissionValues $value, string $filename, string $reason): void
    {
        $form = $value->submission?->form;
        $owner = $form?->user;

        if (! $owner) {
            return;
        }

        // Throttle scan-failure alerts to at most one per form per cooldown
        // window. Quarantine (malicious) alerts are not throttled — each one
        // is a distinct security event worth surfacing.
        if ($reason === 'scan_failed') {
            $lock = "scan-fail-notify:{$owner->id}:{$form->id}";
            if (! Cache::add($lock, true, self::FAILURE_NOTIFY_COOLDOWN)) {
                return;
            }
        }

        $owner->notify(new SubmissionFileScanAlert($form, $filename, $reason));
    }

    /**
     * Called when the job has exhausted all retries. Record the terminal failed
     * state so the file is treated as untrusted by download gating.
     */
    public function failed(Throwable $exception): void
    {
        $value = $this->submissionValue->fresh();

        if (! $value) {
            return;
        }

        Log::error('ScanSubmissionFileJob exhausted retries', [
            'submission_value_id' => $value->id,
            'error' => $exception->getMessage(),
        ]);

        ScanResult::updateOrCreate(
            ['submission_value_id' => $value->id],
            [
                'submission_id' => $value->submission_id,
                'is_malicious' => false,
                'status' => ScanResult::STATUS_FAILED,
                'scanner_used' => 'pandora',
                'filename' => basename((string) $value->value),
            ]
        );

        $this->notifyOwner($value, basename((string) $value->value), 'scan_failed');
    }
}
