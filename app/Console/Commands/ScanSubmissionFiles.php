<?php

namespace App\Console\Commands;

use App\Models\ScanResult;
use App\Models\Submission;
use App\Models\SubmissionValues;
use App\Services\FileScanService;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ScanSubmissionFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:scan-files {submission_id? : The ID of the submission to scan} {--all : Scan all submissions} {--force : Force re-scan even if results exist}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan files in submissions using Pandora. Optionally use --force to re-scan.';

    /**
     * Execute the console command.
     */
    public function handle(FileScanService $scanService): int
    {
        if (! config('services.pandora.enabled', false)) {
            $this->error('Pandora scanning is disabled. Set PANDORA_ENABLED=true to enable.');

            return Command::FAILURE;
        }

        $forceScan = $this->option('force');

        if ($this->option('all')) {
            return $this->scanAll($scanService, $forceScan);
        }

        $submissionId = $this->argument('submission_id');
        if (! $submissionId) {
            $this->error('Please provide a submission ID or use the --all option.');

            return Command::INVALID;
        }

        $submission = Submission::find($submissionId);
        if (! $submission) {
            $this->error("Submission with ID {$submissionId} not found.");

            return Command::FAILURE;
        }

        $stats = ['scanned' => 0, 'created' => 0, 'updated' => 0, 'failed' => 0];
        $this->scanSubmission($submission, $scanService, $forceScan, $stats);
        $this->printSummary(1, $stats);

        return Command::SUCCESS;
    }

    protected function scanAll(FileScanService $scanService, bool $forceScan): int
    {
        $total = Submission::count();
        $this->info("Scanning files from {$total} submissions...");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $stats = ['scanned' => 0, 'created' => 0, 'updated' => 0, 'failed' => 0];

        Submission::chunkById(100, function ($submissions) use ($scanService, $forceScan, &$stats, $bar) {
            foreach ($submissions as $submission) {
                $this->scanSubmission($submission, $scanService, $forceScan, $stats);
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->printSummary($total, $stats);

        return Command::SUCCESS;
    }

    protected function scanSubmission(Submission $submission, FileScanService $scanService, bool $forceScan, array &$stats): void
    {
        $fileValues = SubmissionValues::where('submission_id', $submission->id)
            ->whereHas('field', fn ($q) => $q->where('type', 'file'))
            ->get();

        foreach ($fileValues as $value) {
            $existingResult = ScanResult::where('submission_value_id', $value->id)->first();

            if ($existingResult && ! $forceScan) {
                continue;
            }

            $filePathInStorage = $value->value;
            $filename = basename($filePathInStorage);

            if (! Storage::disk('private')->exists($filePathInStorage)) {
                $this->line(" Skipping missing file: {$filename}");

                continue;
            }

            $fullPath = Storage::disk('private')->path($filePathInStorage);
            $mimeType = Storage::disk('private')->mimeType($filePathInStorage);

            $uploadedFile = new UploadedFile($fullPath, $filename, $mimeType, null, true);
            $scanResultData = $scanService->scanFile($uploadedFile);
            $stats['scanned']++;

            if ($scanResultData['success']) {
                if ($existingResult) {
                    $existingResult->update([
                        'is_malicious' => $scanResultData['is_malicious'],
                        'scan_results' => $scanResultData['scan_results'],
                        'scanner_used' => 'pandora',
                        'filename' => $filename,
                    ]);
                    $stats['updated']++;
                } else {
                    ScanResult::create([
                        'submission_id' => $submission->id,
                        'submission_value_id' => $value->id,
                        'is_malicious' => $scanResultData['is_malicious'],
                        'scan_results' => $scanResultData['scan_results'],
                        'scanner_used' => 'pandora',
                        'filename' => $filename,
                    ]);
                    $stats['created']++;
                }
            } else {
                $this->line(" Scan failed for {$filename}: {$scanResultData['message']}");
                Log::error("CLI scan failed for {$filename}", ['error' => $scanResultData['message']]);
                $stats['failed']++;
            }
        }
    }

    protected function printSummary(int $totalSubmissions, array $stats): void
    {
        $this->info('Scanning Summary:');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Submissions processed', $totalSubmissions],
                ['Files scanned', $stats['scanned']],
                ['New results', $stats['created']],
                ['Updated results', $stats['updated']],
                ['Failed scans', $stats['failed']],
            ]
        );
    }
}
