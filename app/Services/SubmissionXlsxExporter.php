<?php

namespace App\Services;

use App\Models\Form;
use App\Models\Submission;
use Illuminate\Support\Collection;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;
use Throwable;

final class SubmissionXlsxExporter
{
    public function export(Form $form): string
    {
        $form->load([
            'categories' => fn ($query) => $query->orderBy('order'),
            'categories.fields' => fn ($query) => $query->orderBy('order'),
        ]);

        $fields = $form->categories->flatMap(
            fn ($category) => $category->fields
                ->reject(fn ($field) => in_array($field->type, ['header', 'description'], true))
                ->map(fn ($field) => [
                    'id' => $field->id,
                    'header' => $category->name.' - '.$field->label,
                    'type' => $field->type,
                ])
        )->values();

        $headers = [
            'Submission ID',
            'Status',
            'Submitted By',
            'Email',
            'Submitted At',
            'Last Updated',
            ...$fields->pluck('header')->all(),
        ];

        $tempPath = tempnam(sys_get_temp_dir(), 'submission-export-');

        if ($tempPath === false) {
            throw new RuntimeException('Unable to create a temporary XLSX export file.');
        }

        $options = new Options;
        $options->setColumnWidth(24, 1, 2, 3, 4, 5, 6);
        if ($fields->isNotEmpty()) {
            $options->setColumnWidthForRange(30, 7, 6 + $fields->count());
        }

        $writer = new Writer($options);
        $writerOpened = false;

        try {
            $writer->openToFile($tempPath);
            $writerOpened = true;

            $sheet = $writer->getCurrentSheet();
            $sheet->setName('Submissions');
            $sheet->setSheetView((new SheetView)->setFreezeRow(2));

            $headerStyle = (new Style)
                ->setFontBold()
                ->setFontColor(Color::WHITE)
                ->setBackgroundColor('0369A1')
                ->setShouldWrapText();

            $writer->addRow($this->row($headers, $headerStyle));

            $form->submissions()
                ->with(['user', 'values'])
                ->lazyById(100)
                ->each(function (Submission $submission) use ($fields, $writer): void {
                    $values = $submission->values->keyBy('form_field_id');
                    $row = [
                        $submission->id,
                        $submission->status,
                        $submission->user?->name ?? 'Anonymous',
                        $submission->user?->email ?? '',
                        $submission->created_at?->format('Y-m-d H:i:s') ?? '',
                        $submission->updated_at?->format('Y-m-d H:i:s') ?? '',
                    ];

                    foreach ($fields as $field) {
                        $value = $values->get($field['id'])?->value;

                        if ($field['type'] === 'file' && $value) {
                            $value = route('submissions.download', [
                                'submission' => $submission->id,
                                'filename' => basename($value),
                            ]);
                        }

                        $row[] = $value ?? '';
                    }

                    $writer->addRow($this->row($row));
                });

            $writer->close();

            return $tempPath;
        } catch (Throwable $exception) {
            if ($writerOpened) {
                try {
                    $writer->close();
                } catch (Throwable) {
                    // Preserve the exception that caused the export to fail.
                }
            }

            @unlink($tempPath);

            throw $exception;
        }
    }

    /**
     * Use explicit string cells so user-entered values beginning with "="
     * remain text rather than becoming spreadsheet formulas.
     *
     * @param  Collection<int, mixed>|array<int, mixed>  $values
     */
    private function row(Collection|array $values, ?Style $style = null): Row
    {
        $cells = collect($values)
            ->map(static fn ($value) => new StringCell((string) ($value ?? ''), null))
            ->all();

        return new Row($cells, $style);
    }
}
