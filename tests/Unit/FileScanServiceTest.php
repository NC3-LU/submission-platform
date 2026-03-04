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
        Storage::fake('local');
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

        $file = UploadedFile::fake()->createWithContent('document.pdf', 'fake pdf content');
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

        $file = UploadedFile::fake()->createWithContent('malware.exe', 'fake malware content');
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

        $file = UploadedFile::fake()->createWithContent('suspicious.doc', 'fake suspicious content');
        $result = $this->service->scanFile($file);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['is_malicious']);
    }

    public function test_handles_submit_failure(): void
    {
        Http::fake([
            '*/submit' => Http::response('Server Error', 500),
        ]);

        $file = UploadedFile::fake()->createWithContent('document.pdf', 'fake pdf content');
        $result = $this->service->scanFile($file);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('failed to accept', $result['message']);
    }

    public function test_handles_missing_task_id(): void
    {
        Http::fake([
            '*/submit' => Http::response(['success' => true]),
        ]);

        $file = UploadedFile::fake()->createWithContent('document.pdf', 'fake pdf content');
        $result = $this->service->scanFile($file);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('no task ID', $result['message']);
    }

    public function test_handles_connection_timeout(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Connection timeout');
        });

        $file = UploadedFile::fake()->createWithContent('document.pdf', 'fake pdf content');
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
            '*/task_status*' => Http::response([
                'success' => true,
                'taskId' => 'test-task-slow',
                'status' => 'RUNNING',
            ]),
        ]);

        $file = UploadedFile::fake()->createWithContent('document.pdf', 'fake pdf content');
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

        $file = UploadedFile::fake()->createWithContent('document.pdf', 'fake pdf content');
        $this->service->scanFile($file);

        $files = Storage::allFiles('temp/scans');
        $this->assertEmpty($files);
    }

    public function test_cleans_up_temp_files_on_failure(): void
    {
        Http::fake([
            '*/submit' => Http::response('Error', 500),
        ]);

        $file = UploadedFile::fake()->createWithContent('document.pdf', 'fake pdf content');
        $this->service->scanFile($file);

        $files = Storage::allFiles('temp/scans');
        $this->assertEmpty($files);
    }
}
