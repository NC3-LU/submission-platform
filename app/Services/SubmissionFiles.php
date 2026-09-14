<?php

namespace App\Services;

use App\Models\ScanResult;
use App\Models\SubmissionValues;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class SubmissionFiles
{
    public function blocked(SubmissionValues $value): bool
    {
        return config('services.pandora.enabled', false)
            && config('services.pandora.block_malicious', true)
            && $value->scanResult?->status !== ScanResult::STATUS_CLEAN;
    }

    public function metadata(SubmissionValues $value): ?array
    {
        if (! $this->valid($value)) {
            return null;
        }

        return [
            'filename' => $this->filename($value),
            'scan_status' => $value->scanResult?->status ?? 'pending',
            'download_blocked' => $this->blocked($value),
            'download_url' => route('api.forms.submissions.files.show', [
                'form' => $value->submission->form_id, 'submission' => $value->submission_id, 'value' => $value->id,
            ]),
        ];
    }

    public function download(SubmissionValues $value): StreamedResponse
    {
        abort_unless($this->valid($value), 404, 'File not found.');
        abort_if($this->blocked($value), 423, 'This file is awaiting a malware scan or has been blocked.');
        $disk = Storage::disk('private');
        abort_unless($disk->exists($value->value), 404, 'File not found.');

        return $disk->download($value->value, $this->filename($value), [
            'Content-Type' => $disk->mimeType($value->value) ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    private function valid(SubmissionValues $value): bool
    {
        return $value->field?->type === 'file'
            && $value->field->form_id === $value->submission?->form_id
            && is_string($value->value) && $value->submission->ownsFilePath($value->value);
    }

    private function filename(SubmissionValues $value): string
    {
        return preg_replace('/[\x00-\x1F\x7F]/', '_', basename($value->value)) ?: 'attachment';
    }
}
