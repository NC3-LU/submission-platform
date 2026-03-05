<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileScanService
{
    protected string $pandoraUrl;

    protected int $timeout;

    protected int $pollInterval;

    public function __construct()
    {
        $this->pandoraUrl = config('services.pandora.url', 'http://pandora:6100');
        $this->timeout = (int) config('services.pandora.timeout', 30);
        $this->pollInterval = (int) config('services.pandora.poll_interval', 2);
    }

    /**
     * Scan an uploaded file for malware via Pandora's async API.
     *
     * Submits the file to POST /submit, then polls GET /task_status
     * until a terminal status is reached or timeout expires.
     */
    public function scanFile(UploadedFile $file): array
    {
        try {
            $uniqueFilename = Str::uuid()->toString().'_'.$file->getClientOriginalName();
            $tempPath = $file->storeAs('temp/scans', $uniqueFilename);
            $fullPath = Storage::path($tempPath);

            // Step 1: Submit file to Pandora
            $submitResponse = Http::timeout($this->timeout)
                ->withOptions(['proxy' => ''])
                ->attach('file', fopen($fullPath, 'r'), $file->getClientOriginalName())
                ->post("{$this->pandoraUrl}/submit", ['validity' => 0]);

            Storage::delete($tempPath);

            if (! $submitResponse->successful()) {
                Log::error('Pandora submit failed', [
                    'status' => $submitResponse->status(),
                    'body' => $submitResponse->body(),
                    'file' => $file->getClientOriginalName(),
                ]);

                return ['success' => false, 'message' => 'Pandora failed to accept file'];
            }

            $submitData = $submitResponse->json();

            if (empty($submitData['taskId'])) {
                Log::error('Pandora submit returned no taskId', ['response' => $submitData]);

                return ['success' => false, 'message' => 'Pandora returned no task ID'];
            }

            $taskId = $submitData['taskId'];
            $seed = $submitData['seed'] ?? null;

            // Step 2: Poll for results
            return $this->pollForResults($taskId, $seed, $file->getClientOriginalName());
        } catch (\Exception $e) {
            // Clean up temp file if it exists
            if (isset($tempPath)) {
                Storage::delete($tempPath);
            }

            Log::error('Exception during file scan', [
                'message' => $e->getMessage(),
                'file' => $file->getClientOriginalName(),
            ]);

            return ['success' => false, 'message' => 'Error scanning file: '.$e->getMessage()];
        }
    }

    /**
     * Poll Pandora's task_status endpoint until a terminal status or timeout.
     */
    protected function pollForResults(string $taskId, ?string $seed, string $filename): array
    {
        $startTime = time();

        while ((time() - $startTime) < $this->timeout) {
            $query = ['task_id' => $taskId];
            if ($seed) {
                $query['seed'] = $seed;
            }

            $response = Http::timeout(10)
                ->withOptions(['proxy' => ''])
                ->get("{$this->pandoraUrl}/task_status", $query);

            if (! $response->successful()) {
                Log::warning('Pandora task_status request failed', [
                    'taskId' => $taskId,
                    'status' => $response->status(),
                ]);
                sleep($this->pollInterval);

                continue;
            }

            $data = $response->json();
            $status = strtoupper($data['status'] ?? '');

            if (in_array($status, ['ERROR', 'OVERWRITE'])) {
                Log::warning('Pandora scan returned error status', [
                    'taskId' => $taskId,
                    'status' => $status,
                    'filename' => $filename,
                ]);

                return ['success' => false, 'message' => "Pandora scan returned status: {$status}"];
            }

            if (in_array($status, ['CLEAN', 'WARN', 'ALERT'])) {
                $isMalicious = in_array($status, ['ALERT', 'WARN']);

                Log::info('Pandora scan complete', [
                    'taskId' => $taskId,
                    'status' => $status,
                    'is_malicious' => $isMalicious,
                    'filename' => $filename,
                ]);

                return [
                    'success' => true,
                    'is_malicious' => $isMalicious,
                    'scan_results' => $data,
                ];
            }

            // Still processing — wait and retry
            sleep($this->pollInterval);
        }

        Log::warning('Pandora scan timed out', [
            'taskId' => $taskId,
            'timeout' => $this->timeout,
            'filename' => $filename,
        ]);

        return ['success' => false, 'message' => "Scan timed out after {$this->timeout}s"];
    }
}
