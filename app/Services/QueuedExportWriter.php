<?php

namespace App\Services;

use App\Models\Submission;
use App\Models\SubmissionExport;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;

final class QueuedExportWriter
{
    public function write(SubmissionExport $export): array
    {
        $form = $export->form;
        $fields = $form->fields()->whereNotIn('type', ['header', 'description'])->orderBy('order')->orderBy('id')->get();
        if ($fields->count() > config('integrations.exports.max_fields')) {
            throw new ExportLimitExceeded;
        }
        $disk = Storage::disk('private');
        $directory = 'exports/'.$export->id;
        $disk->makeDirectory($directory);
        $path = $disk->path($export->artifactPath());
        $stream = null;
        $writer = null;
        $rows = 0;
        $bytes = 0;
        $started = microtime(true);
        try {
            if ($export->format === 'json') {
                $stream = fopen($path, 'wb');
                if (! $stream) {
                    throw new \RuntimeException('Cannot open export.');
                }
                $this->put($stream, '{"form":'.json_encode(['id' => $form->id, 'title' => $form->title], JSON_THROW_ON_ERROR).',"submissions":[', $bytes);
            } else {
                $options = new Options;
                $options->setTempFolder($disk->path($directory));
                $writer = new Writer($options);
                $writer->openToFile($path);
                $writer->addRow($this->row(['Submission ID', 'Status', 'Created at', ...$fields->pluck('label')->all()]));
            }
            foreach ($form->submissions()->whereIn('status', Submission::REVIEW_STATUSES)->where('created_at', '<=', $export->created_at)
                ->with(['values.field', 'values.scanResult'])->lazyById(5) as $submission) {
                $submission->setRelation('form', $form);
                if (! Gate::forUser($export->user)->allows('export', $submission)) {
                    continue;
                }
                if (++$rows > config('integrations.exports.max_rows') || microtime(true) - $started > 100) {
                    throw new ExportLimitExceeded;
                }
                $values = $submission->values->keyBy('form_field_id');
                $answers = [];
                $cells = [$submission->id, $submission->status, $submission->created_at->toIso8601String()];
                foreach ($fields as $field) {
                    $value = $values->get($field->id);
                    $value?->setRelation('submission', $submission);
                    $answer = $field->type === 'file' && $value ? app(SubmissionFiles::class)->metadata($value) : $value?->value;
                    $answers[] = ['field_id' => $field->id, 'label' => $field->label, 'value' => $answer];
                    $cells[] = is_array($answer) ? ($answer['download_url'] ?? '') : ($answer ?? '');
                }
                $json = json_encode(['id' => $submission->id, 'status' => $submission->status, 'created_at' => $submission->created_at->toIso8601String(), 'values' => $answers], JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
                if ($stream) {
                    $this->put($stream, ($rows > 1 ? ',' : '').$json, $bytes);
                } else {
                    $bytes += strlen($json);
                    if ($bytes > config('integrations.exports.max_bytes')) {
                        throw new ExportLimitExceeded;
                    }
                    $writer->addRow($this->row($cells));
                }
            }
            if ($stream) {
                $this->put($stream, ']}', $bytes);
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
            $writer?->close();
        }
        clearstatcache(true, $path);
        $size = filesize($path);
        if ($size > config('integrations.exports.max_bytes')) {
            throw new ExportLimitExceeded;
        }

        return ['row_count' => $rows, 'bytes' => $size, 'path' => $export->artifactPath()];
    }

    private function put($stream, string $text, int &$bytes): void
    {
        $bytes += strlen($text);
        if ($bytes > config('integrations.exports.max_bytes')) {
            throw new ExportLimitExceeded;
        }
        if (fwrite($stream, $text) !== strlen($text)) {
            throw new \RuntimeException('Cannot write export.');
        }
    }

    private function row(array $values): Row
    {
        return new Row(array_map(fn ($value) => new StringCell((string) $value, null), $values));
    }
}
