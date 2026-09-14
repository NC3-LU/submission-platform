<?php

namespace App\Services;

use App\Jobs\GenerateSubmissionExport;
use App\Models\ApiToken;
use App\Models\Form;
use App\Models\IntegrationEvent;
use App\Models\Submission;
use App\Models\SubmissionExport;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

final class SubmissionExports
{
    public function create(Form $form, ApiToken $token, string $format): SubmissionExport
    {
        $connection = config('integrations.queue_connection');
        abort_if(in_array(config("queue.connections.$connection.driver"), ['sync', 'null', 'deferred', 'background', null], true), 503, 'An asynchronous integration queue is required.');

        return DB::transaction(function () use ($form, $token, $format, $connection) {
            User::whereKey($token->user_id)->lockForUpdate()->firstOrFail();
            $form = Form::whereKey($form->id)->lockForUpdate()->firstOrFail();
            abort_unless(Gate::forUser($token->user)->allows('exportSubmissions', $form), 404);
            foreach (['user_id' => $token->user_id, 'form_id' => $form->id] as $column => $id) {
                $base = SubmissionExport::where($column, $id);
                abort_if((clone $base)->whereIn('status', ['queued', 'processing'])->count() >= 2
                    || (clone $base)->where('created_at', '>=', now()->subDay())->count() >= ($column === 'user_id' ? 10 : 20), 429, 'Export quota reached. Try again later.');
            }
            abort_if($form->fields()->count() > config('integrations.exports.max_fields')
                || $form->submissions()->whereIn('status', Submission::REVIEW_STATUSES)->count() > config('integrations.exports.max_rows'), 422, 'This form exceeds the export size limit.');
            $export = SubmissionExport::create(['form_id' => $form->id, 'user_id' => $token->user_id, 'token_id' => $token->id,
                'format' => $format, 'status' => 'queued', 'expires_at' => now()->addHours(config('integrations.exports.retention_hours'))]);
            IntegrationEvent::record('export.created', $form->id, $export->id, $token->user_id, $token->id, ['format' => $format]);
            GenerateSubmissionExport::dispatch($export->id)->onConnection($connection)->afterCommit();

            return $export;
        });
    }

    public function fail(string $id, string $code): void
    {
        DB::transaction(function () use ($id, $code) {
            $export = SubmissionExport::whereKey($id)->lockForUpdate()->first();
            if (! $export || ! in_array($export->status, ['queued', 'processing'], true)) {
                return;
            }
            $export->update(['status' => 'failed', 'error_code' => $code, 'path' => null]);
            FileCleanup::schedule('private', [$export->artifactPath()]);
            IntegrationEvent::record('export.failed', $export->form_id, $id, $export->user_id, $export->token_id, ['error_code' => $code]);
        });
    }

    public function prune(): void
    {
        SubmissionExport::whereIn('status', ['queued', 'processing'])->where('updated_at', '<', now()->subMinutes(30))
            ->lazyById(100)->each(fn ($export) => $this->fail($export->id, 'worker_unavailable'));
        SubmissionExport::where('expires_at', '<=', now())->where('status', '!=', 'expired')->lazyById(100)->each(function ($record) {
            DB::transaction(function () use ($record) {
                $export = SubmissionExport::whereKey($record->id)->lockForUpdate()->first();
                if (! $export || $export->status === 'expired') {
                    return;
                }
                FileCleanup::schedule('private', [$export->artifactPath()]);
                $export->update(['status' => 'expired', 'path' => null]);
                IntegrationEvent::record('export.deleted', $export->form_id, $export->id, null);
            });
        });
        // OpenSpout can leave packaging files behind if a worker is killed.
        SubmissionExport::whereIn('status', ['failed', 'expired'])->lazyById(100)->each(function ($export) {
            FileCleanup::schedule('private', Storage::disk('private')->allFiles('exports/'.$export->id));
        });
        FileCleanup::drain();
    }
}
