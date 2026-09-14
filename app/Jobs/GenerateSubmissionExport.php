<?php

namespace App\Jobs;

use App\Models\IntegrationEvent;
use App\Models\SubmissionExport;
use App\Services\ExportLimitExceeded;
use App\Services\QueuedExportWriter;
use App\Services\SubmissionExports;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class GenerateSubmissionExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public bool $failOnTimeout = true;

    public function __construct(public readonly string $exportId) {}

    public function handle(): void
    {
        $export = DB::transaction(function () {
            $export = SubmissionExport::whereKey($this->exportId)->lockForUpdate()->first();
            if (! $export || $export->status !== 'queued') {
                return null;
            }
            $export->update(['status' => 'processing']);

            return $export;
        });
        if (! $export) {
            return;
        }
        $complete = false;
        try {
            if (! $this->authorized($export) || $export->expires_at->isPast()) {
                app(SubmissionExports::class)->fail($export->id, 'access_revoked');

                return;
            }
            $artifact = app(QueuedExportWriter::class)->write($export);
            $complete = DB::transaction(function () use ($export, $artifact) {
                $current = SubmissionExport::whereKey($export->id)->lockForUpdate()->firstOrFail();
                if ($current->status !== 'processing' || ! $this->authorized($current) || $current->expires_at->isPast()) {
                    return false;
                }
                $current->update([...$artifact, 'status' => 'completed']);
                IntegrationEvent::record('export.completed', $current->form_id, $current->id, $current->user_id, $current->token_id, ['row_count' => $artifact['row_count'], 'bytes' => $artifact['bytes']]);

                return true;
            });
            if (! $complete) {
                app(SubmissionExports::class)->fail($export->id, 'access_revoked');
            }
        } catch (ExportLimitExceeded) {
            app(SubmissionExports::class)->fail($export->id, 'limit_exceeded');
        } catch (Throwable) {
            app(SubmissionExports::class)->fail($export->id, 'generation_failed');
        } finally {
            if (! $complete) {
                Storage::disk('private')->deleteDirectory('exports/'.$export->id);
            }
        }
    }

    private function authorized(SubmissionExport $export): bool
    {
        return $export->user && $export->form && $export->token && ! $export->token->isExpired()
            && $export->token->can('submissions:export')
            && Gate::forUser($export->user)->allows('exportSubmissions', $export->form);
    }

    public function failed(?Throwable $error): void
    {
        app(SubmissionExports::class)->fail($this->exportId, 'generation_failed');
    }
}
