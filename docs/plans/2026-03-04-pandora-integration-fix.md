# Pandora Integration Fix — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Fix the broken Pandora file scanning integration to work with Pandora's real async API, remove dead code, add missing indexes, improve UX, make Pandora Docker services optional via `PANDORA_ENABLED`, and fix all tests.

**Architecture:** Rewrite `FileScanService` to use Pandora's async submit→poll flow (`POST /submit` returns `taskId`, poll `GET /task_status` until terminal). Remove the dead `ScanUploadedFiles` middleware. Use Docker Compose profiles for conditional Pandora service inclusion.

**Tech Stack:** Laravel 12, PHP 8.2, Livewire 3, Filament 3, Docker Compose profiles, PHPUnit

---

### Task 1: Add `poll_interval` to Pandora config

**Files:**
- Modify: `config/services.php:38-43`
- Modify: `.env.example:70-78`

**Step 1: Add poll_interval to config**

In `config/services.php`, replace lines 38-43:

```php
'pandora' => [
    'url' => env('PANDORA_URL', 'http://pandora:6100'),
    'enabled' => env('PANDORA_ENABLED', false),
    'timeout' => env('PANDORA_TIMEOUT', 30),
    'poll_interval' => env('PANDORA_POLL_INTERVAL', 2),
    'block_malicious' => env('PANDORA_BLOCK_MALICIOUS', true),
],
```

Note: default timeout changed from 15 to 30 to allow polling time.

**Step 2: Update .env.example**

Replace the Pandora section (lines 70-78):

```env
# Pandora file scanning (optional)
# Set to 'true' to enable Pandora scanning and Docker services
PANDORA_ENABLED=false
# Pandora service base URL
PANDORA_URL=http://pandora:6100
# Max seconds to wait for scan results (includes polling)
PANDORA_TIMEOUT=30
# Seconds between poll requests to check scan status
PANDORA_POLL_INTERVAL=2
# If true, block uploads flagged as malicious
PANDORA_BLOCK_MALICIOUS=true
```

**Step 3: Commit**

```bash
git add config/services.php .env.example
git commit -m "feat(pandora): add poll_interval config, increase default timeout to 30s"
```

---

### Task 2: Rewrite `FileScanService` for async API

**Files:**
- Rewrite: `app/Services/FileScanService.php`

**Step 1: Replace the entire file**

```php
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
            $uniqueFilename = Str::uuid()->toString() . '_' . $file->getClientOriginalName();
            $tempPath = $file->storeAs('temp/scans', $uniqueFilename);
            $fullPath = Storage::path($tempPath);

            // Step 1: Submit file to Pandora
            $submitResponse = Http::timeout($this->timeout)
                ->attach('file', file_get_contents($fullPath), $file->getClientOriginalName())
                ->post("{$this->pandoraUrl}/submit", ['validity' => 0]);

            Storage::delete($tempPath);

            if (!$submitResponse->successful()) {
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

            return ['success' => false, 'message' => 'Error scanning file: ' . $e->getMessage()];
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
                ->get("{$this->pandoraUrl}/task_status", $query);

            if (!$response->successful()) {
                Log::warning('Pandora task_status request failed', [
                    'taskId' => $taskId,
                    'status' => $response->status(),
                ]);
                sleep($this->pollInterval);
                continue;
            }

            $data = $response->json();
            $status = strtoupper($data['status'] ?? '');

            if (in_array($status, ['CLEAN', 'WARN', 'ALERT', 'ERROR', 'OVERWRITE'])) {
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
```

**Step 2: Verify no syntax errors**

Run: `php -l app/Services/FileScanService.php`
Expected: `No syntax errors detected`

**Step 3: Commit**

```bash
git add app/Services/FileScanService.php
git commit -m "feat(pandora): rewrite FileScanService for async submit/poll API"
```

---

### Task 3: Remove dead `ScanUploadedFiles` middleware

The middleware intercepts `$request->files` but Livewire uploads bypass this entirely. It's dead code.

**Files:**
- Delete: `app/Http/Middleware/ScanUploadedFiles.php`
- Delete: `tests/Feature/ScanUploadedFilesTest.php`
- Modify: `bootstrap/app.php:6,30-33`

**Step 1: Remove middleware from bootstrap/app.php**

In `bootstrap/app.php`, remove the `use` import on line 6:
```php
use App\Http\Middleware\ScanUploadedFiles;
```

Replace lines 30-33:
```php
        $middleware->web(append: [
            FormAccessMiddleware::class,
            ScanUploadedFiles::class,
        ]);
```
with:
```php
        $middleware->web(append: [
            FormAccessMiddleware::class,
        ]);
```

**Step 2: Delete the middleware file**

```bash
rm app/Http/Middleware/ScanUploadedFiles.php
```

**Step 3: Delete the test file**

```bash
rm tests/Feature/ScanUploadedFilesTest.php
```

**Step 4: Verify no references remain**

Run: `grep -r "ScanUploadedFiles" --include="*.php" .`
Expected: no output

**Step 5: Commit**

```bash
git add -A
git commit -m "refactor(pandora): remove dead ScanUploadedFiles middleware

Livewire file uploads bypass standard request files, so this middleware
never intercepted submission uploads. Scanning happens in
SubmissionForm::handleFileUploads() instead."
```

---

### Task 4: Add database indexes migration

**Files:**
- Create: `database/migrations/2026_03_04_000001_add_scan_results_indexes.php`

**Step 1: Create migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scan_results', function (Blueprint $table) {
            $table->index('submission_id');
            $table->index('is_malicious');
        });
    }

    public function down(): void
    {
        Schema::table('scan_results', function (Blueprint $table) {
            $table->dropIndex(['submission_id']);
            $table->dropIndex(['is_malicious']);
        });
    }
};
```

**Step 2: Run migration**

Run: `php artisan migrate`
Expected: migration runs successfully

**Step 3: Commit**

```bash
git add database/migrations/2026_03_04_000001_add_scan_results_indexes.php
git commit -m "perf(pandora): add indexes on scan_results.submission_id and is_malicious"
```

---

### Task 5: Fix `ScanSubmissionFiles` artisan command

**Files:**
- Modify: `app/Console/Commands/ScanSubmissionFiles.php`

**Step 1: Rewrite the handle method to use chunking and progress bar**

Replace the entire `handle()` method (lines 34-157) with:

```php
    public function handle(FileScanService $scanService): int
    {
        if (!config('services.pandora.enabled', false)) {
            $this->error('Pandora scanning is disabled. Set PANDORA_ENABLED=true to enable.');
            return Command::FAILURE;
        }

        $forceScan = $this->option('force');

        if ($this->option('all')) {
            return $this->scanAll($scanService, $forceScan);
        }

        $submissionId = $this->argument('submission_id');
        if (!$submissionId) {
            $this->error('Please provide a submission ID or use the --all option.');
            return Command::INVALID;
        }

        $submission = Submission::find($submissionId);
        if (!$submission) {
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

            if ($existingResult && !$forceScan) {
                continue;
            }

            $filePathInStorage = $value->value;
            $filename = basename($filePathInStorage);

            if (!Storage::disk('private')->exists($filePathInStorage)) {
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
```

**Step 2: Verify syntax**

Run: `php -l app/Console/Commands/ScanSubmissionFiles.php`
Expected: `No syntax errors detected`

**Step 3: Commit**

```bash
git add app/Console/Commands/ScanSubmissionFiles.php
git commit -m "fix(pandora): use chunking and progress bar in scan-files command"
```

---

### Task 6: Add malicious file notification in SubmissionForm

**Files:**
- Modify: `app/Livewire/SubmissionForm.php:671-689`

**Step 1: Add dispatch notification after malicious file removal**

In `app/Livewire/SubmissionForm.php`, replace lines 673-689 (the malicious file handling block):

```php
                        if ($scanResultData['is_malicious'] && config('services.pandora.block_malicious', true)) {
                            Log::warning('Detected malicious file after upload, removing', [
                                'submission_id' => $this->submission->id,
                                'filename' => $originalName,
                            ]);

                            Storage::disk('private')->delete($newPath);
                            $submissionValue->update(['value' => '[REMOVED-MALICIOUS]: ' . $originalName]);
                            $this->dispatch('error', 'The file "' . $originalName . '" was flagged as malicious and has been removed.');
                        }
```

This replaces the old block that had TODO comments about notifications. The `dispatch('error', ...)` sends a Livewire event that the frontend toast system already handles.

**Step 2: Commit**

```bash
git add app/Livewire/SubmissionForm.php
git commit -m "feat(pandora): notify user when malicious file is removed from submission"
```

---

### Task 7: Fix Filament ScanResultResource filter

**Files:**
- Modify: `app/Filament/Resources/ScanResultResource.php:117-121`

**Step 1: Remove invalid clamav option from scanner filter**

Replace lines 117-121:

```php
                Tables\Filters\SelectFilter::make('scanner_used')
                    ->options([
                        'pandora' => 'Pandora',
                    ]),
```

**Step 2: Commit**

```bash
git add app/Filament/Resources/ScanResultResource.php
git commit -m "fix(filament): remove invalid clamav option from scanner filter"
```

---

### Task 8: Docker Compose profiles for optional Pandora

**Files:**
- Modify: `docker/pandora/pandora.yml`
- Modify: `scripts/setup-pandora.sh`
- Modify: `docker-compose.yml:67-68` (Pandora env passthrough already exists)

**Step 1: Add profiles to all services in pandora.yml**

Replace the entire `docker/pandora/pandora.yml`:

```yaml
services:
  redis:
    image: redis:7
    profiles: [pandora]
    networks: [app_network]
    restart: unless-stopped

  kvrocks:
    image: apache/kvrocks:latest
    profiles: [pandora]
    networks: [app_network]
    restart: unless-stopped

  clamav:
    image: clamav/clamav
    profiles: [pandora]
    volumes:
      - clamav-socket:/var/run/clamav
      - ./docker/pandora/config/clamav/clamd.conf:/etc/clamav/clamd.conf:ro
      - ./docker/pandora/config/clamav/freshclam.conf:/etc/clamav/freshclam.conf:ro
    networks: [app_network]
    environment:
    - HTTP_PROXY=${PROXY}
    - HTTPS_PROXY=${PROXY}
    - http_proxy=${PROXY}
    - https_proxy=${PROXY}
    healthcheck:
      test: ["CMD", "clamdscan", "--ping", "1"]
      start_period: 180s
      interval:     30s
      timeout:      5s
      retries:      3
    restart: unless-stopped

  pandora:
    image: ghcr.io/pandora-analysis/pandora:latest
    profiles: [pandora]
    depends_on: [redis, kvrocks, clamav]
    expose: ["6100"]
    environment:
      - PANDORA_REDIS_HOST=redis
      - PANDORA_KVROCKS_HOST=kvrocks
      - HTTP_PROXY=${PROXY}
      - HTTPS_PROXY=${PROXY}
      - http_proxy=${PROXY}
      - https_proxy=${PROXY}
    working_dir: /pandora
    tty: true
    command:
      - /bin/sh
      - -c
      - |
          echo "[pandora] waiting for dependencies..."
          sleep 60
          echo "[pandora] starting service"
          poetry run start
          tail -F ./LICENSE
    env_file:
      - ./docker/pandora/pandora.env
    volumes:
      - pandora_cache:/pandora/cache
      - pandora_storage:/pandora/storage
      - clamav-socket:/var/run/clamav
      - ./docker/pandora/config/generic.json:/pandora/config/generic.json:ro
      - ./docker/pandora/workers:/pandora/pandora/workers:ro
      - ./docker/pandora/yara_rules:/pandora/yara_rules:ro
    networks: [app_network]
    restart: unless-stopped

volumes:
  pandora_cache:
  pandora_storage:
  clamav-socket:
```

Note: removed the `networks:` top-level section (the app_network is defined in docker-compose.yml). Added `profiles: [pandora]` to all four services.

**Step 2: Update setup-pandora.sh to use --profile**

Replace `scripts/setup-pandora.sh`:

```bash
#!/usr/bin/env bash
#
# setup-pandora.sh <enabled true|false> [proxy]
#
# Starts the Pandora stack alongside the main app if enabled.
# Uses Docker Compose profiles — Pandora services have `profiles: [pandora]`.
# --------------------------------------------------------------------

set -euo pipefail

PANDORA_ENABLED="${1:-false}"
PROXY="${2:-${PROXY:-}}"
PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

if [[ "$PANDORA_ENABLED" != "true" ]]; then
  echo "→ Pandora disabled – skipping."
  exit 0
fi

echo "→ Ensuring Docker network…"
docker network inspect app_network >/dev/null 2>&1 \
  || docker network create app_network

if [[ -n "$PROXY" ]]; then
  export HTTP_PROXY="$PROXY"
  export HTTPS_PROXY="$PROXY"
  export http_proxy="$PROXY"
  export https_proxy="$PROXY"
fi

cd "$PROJECT_ROOT"

export COMPOSE_FILE="$PROJECT_ROOT/docker-compose.yml:$PROJECT_ROOT/docker/pandora/pandora.yml"

if command -v docker-compose >/dev/null 2>&1; then
  COMPOSE='docker-compose'
else
  COMPOSE='docker compose'
fi

echo "→ Pulling images & starting Pandora stack…"
$COMPOSE --profile pandora up -d --pull always
```

**Step 3: Commit**

```bash
git add docker/pandora/pandora.yml scripts/setup-pandora.sh
git commit -m "feat(docker): use compose profiles for optional Pandora install

When PANDORA_ENABLED=true, run with --profile pandora to include
the scanning stack (Pandora + ClamAV + Redis + KVRocks).
When false, only app + db run."
```

---

### Task 9: Create ScanResultFactory

**Files:**
- Create: `database/factories/ScanResultFactory.php`

**Step 1: Create the factory**

```php
<?php

namespace Database\Factories;

use App\Models\ScanResult;
use App\Models\Submission;
use App\Models\SubmissionValues;
use Illuminate\Database\Eloquent\Factories\Factory;

class ScanResultFactory extends Factory
{
    protected $model = ScanResult::class;

    public function definition(): array
    {
        return [
            'submission_id' => Submission::factory(),
            'submission_value_id' => SubmissionValues::factory(),
            'is_malicious' => false,
            'scan_results' => ['status' => 'CLEAN', 'taskId' => $this->faker->uuid()],
            'scanner_used' => 'pandora',
            'filename' => $this->faker->word() . '.pdf',
        ];
    }

    public function malicious(): static
    {
        return $this->state(fn () => [
            'is_malicious' => true,
            'scan_results' => ['status' => 'ALERT', 'taskId' => $this->faker->uuid()],
        ]);
    }
}
```

Note: If `SubmissionValues` doesn't have a factory, use a raw ID instead — check existing factories. The `submission_value_id` may need to be set manually in tests.

**Step 2: Commit**

```bash
git add database/factories/ScanResultFactory.php
git commit -m "test(pandora): add ScanResultFactory with malicious state"
```

---

### Task 10: Rewrite FileScanService tests

**Files:**
- Rewrite: `tests/Unit/FileScanServiceTest.php`

**Step 1: Replace the entire test file**

```php
<?php

namespace Tests\Unit;

use App\Services\FileScanService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FileScanServiceTest extends TestCase
{
    protected FileScanService $service;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.pandora.url' => 'http://pandora:6100']);
        config(['services.pandora.timeout' => 5]);
        config(['services.pandora.poll_interval' => 0]);
        $this->service = app(FileScanService::class);
    }

    protected function tearDown(): void
    {
        try {
            Storage::deleteDirectory('temp/scans');
        } catch (\Exception $e) {
        }
        parent::tearDown();
    }

    public function test_scans_clean_file_successfully(): void
    {
        Http::fake([
            '*/submit' => Http::response([
                'success' => true,
                'taskId' => 'test-task-123',
                'seed' => 'test-seed',
            ]),
            '*/task_status*' => Http::response([
                'success' => true,
                'taskId' => 'test-task-123',
                'status' => 'CLEAN',
            ]),
        ]);

        $file = UploadedFile::fake()->create('document.pdf', 100);
        $result = $this->service->scanFile($file);

        $this->assertTrue($result['success']);
        $this->assertFalse($result['is_malicious']);
        $this->assertEquals('CLEAN', $result['scan_results']['status']);
    }

    public function test_detects_malicious_file_alert_status(): void
    {
        Http::fake([
            '*/submit' => Http::response([
                'success' => true,
                'taskId' => 'test-task-456',
                'seed' => 'test-seed',
            ]),
            '*/task_status*' => Http::response([
                'success' => true,
                'taskId' => 'test-task-456',
                'status' => 'ALERT',
            ]),
        ]);

        $file = UploadedFile::fake()->create('malware.exe', 100);
        $result = $this->service->scanFile($file);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['is_malicious']);
    }

    public function test_detects_malicious_file_warn_status(): void
    {
        Http::fake([
            '*/submit' => Http::response([
                'success' => true,
                'taskId' => 'test-task-789',
                'seed' => 'test-seed',
            ]),
            '*/task_status*' => Http::response([
                'success' => true,
                'taskId' => 'test-task-789',
                'status' => 'WARN',
            ]),
        ]);

        $file = UploadedFile::fake()->create('suspicious.doc', 100);
        $result = $this->service->scanFile($file);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['is_malicious']);
    }

    public function test_handles_submit_failure(): void
    {
        Http::fake([
            '*/submit' => Http::response('Server Error', 500),
        ]);

        $file = UploadedFile::fake()->create('document.pdf', 100);
        $result = $this->service->scanFile($file);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('failed to accept', $result['message']);
    }

    public function test_handles_missing_task_id(): void
    {
        Http::fake([
            '*/submit' => Http::response(['success' => true]),
        ]);

        $file = UploadedFile::fake()->create('document.pdf', 100);
        $result = $this->service->scanFile($file);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('no task ID', $result['message']);
    }

    public function test_handles_connection_timeout(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Connection timeout');
        });

        $file = UploadedFile::fake()->create('document.pdf', 100);
        $result = $this->service->scanFile($file);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Error scanning file', $result['message']);
    }

    public function test_handles_poll_timeout(): void
    {
        config(['services.pandora.timeout' => 1]);
        $this->service = app(FileScanService::class);

        Http::fake([
            '*/submit' => Http::response([
                'success' => true,
                'taskId' => 'test-task-slow',
                'seed' => 'test-seed',
            ]),
            // Return non-terminal status forever — simulates a scan that never finishes
            '*/task_status*' => Http::response([
                'success' => true,
                'taskId' => 'test-task-slow',
                'status' => 'RUNNING',
            ]),
        ]);

        $file = UploadedFile::fake()->create('document.pdf', 100);
        $result = $this->service->scanFile($file);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('timed out', $result['message']);
    }

    public function test_cleans_up_temp_files_on_success(): void
    {
        Http::fake([
            '*/submit' => Http::response([
                'success' => true,
                'taskId' => 'test-cleanup',
                'seed' => 'test-seed',
            ]),
            '*/task_status*' => Http::response([
                'success' => true,
                'taskId' => 'test-cleanup',
                'status' => 'CLEAN',
            ]),
        ]);

        $file = UploadedFile::fake()->create('document.pdf', 100);
        $this->service->scanFile($file);

        $files = Storage::allFiles('temp/scans');
        $this->assertEmpty($files);
    }

    public function test_cleans_up_temp_files_on_failure(): void
    {
        Http::fake([
            '*/submit' => Http::response('Error', 500),
        ]);

        $file = UploadedFile::fake()->create('document.pdf', 100);
        $this->service->scanFile($file);

        $files = Storage::allFiles('temp/scans');
        $this->assertEmpty($files);
    }
}
```

**Step 2: Run the tests**

Run: `./vendor/bin/phpunit tests/Unit/FileScanServiceTest.php -v`
Expected: All 9 tests pass (the poll_timeout test may be slow due to 1s sleep — that's fine)

**Step 3: Commit**

```bash
git add tests/Unit/FileScanServiceTest.php
git commit -m "test(pandora): rewrite FileScanService tests for async submit/poll API"
```

---

### Task 11: Fix CLAUDE.md documentation

**Files:**
- Modify: `CLAUDE.md`

**Step 1: Fix the artisan command reference**

Find `app:scan-submissions` in the CLAUDE.md file and replace with `app:scan-files`.

Also add the new env var `PANDORA_POLL_INTERVAL` to the environment section if it lists Pandora vars.

**Step 2: Commit**

```bash
git add CLAUDE.md
git commit -m "docs: fix artisan command name scan-submissions → scan-files, add PANDORA_POLL_INTERVAL"
```

---

### Task 12: Run full test suite and verify

**Step 1: Run all tests**

Run: `composer test`
Expected: All tests pass, no regressions

**Step 2: Lint**

Run: `composer lint`
Expected: No style issues (or fix with `composer lint:fix`)

**Step 3: Final commit if any lint fixes needed**

```bash
git add -A
git commit -m "style: fix code style after pandora refactor"
```
