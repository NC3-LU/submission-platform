<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Models\Submission;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubmissionExportController extends Controller
{
    use AuthorizesRequests;
    /**
     * Export single submission to PDF
     *
     * @throws AuthorizationException
     */
    public function exportSubmissionPdf(Form $form, Submission $submission): Response
    {

        // Ensure the submission belongs to the form
        if ($submission->form_id !== $form->id) {
            abort(404);
        }
        $this->authorize('export', $submission);

        // Load the submission with its values
        $submission->load(['values', 'values.field']);

        // Prepare submission data
        $submissionData = $this->prepareSubmissionData($submission);

        $pdf = PDF::loadView('submissions.pdf', [
            'form' => $form,
            'submission' => $submissionData,
        ])->setPaper('a4');

        // Render and add page numbers (bottom-right)
        $pdf->render();
        $canvas = $pdf->getDomPDF()->getCanvas();
        $w = $canvas->get_width();
        $h = $canvas->get_height();
        $font = $pdf->getDomPDF()->getFontMetrics()->get_font('DejaVu Sans', 'normal');
        $canvas->page_text($w - 72, $h - 28, 'Page {PAGE_NUM} of {PAGE_COUNT}', $font, 9, [0.42, 0.45, 0.50]);

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="submission-' . $submission->id . '.pdf"',
        ]);
    }

    /**
     * Export all submissions for a form to CSV
     *
     * @throws AuthorizationException
     */
    public function exportFormCsv(Form $form): StreamedResponse
    {
        $this->authorize('exportAllSubmissions', $form);

        // Load the form with its categories and fields
        $form->load([
            'categories' => function ($query) {
                $query->orderBy('order');
            },
            'categories.fields' => function ($query) {
                $query->orderBy('order');
            },
        ]);

        // Load all submissions with their values
        $submissions = $form->submissions()
            ->with(['values', 'user'])
            ->latest()
            ->get();

        // Create CSV content
        $headers = ['Submission ID', 'Submitted By', 'Submitted At'];
        $fieldIds = [];

        // Build headers from form structure
        foreach ($form->categories as $category) {
            foreach ($category->fields as $field) {
                $headers[] = $category->name . ' - ' . $field->label;
                $fieldIds[] = $field->id;
            }
        }

        $callback = function() use ($submissions, $headers, $fieldIds) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $headers);

            foreach ($submissions as $submission) {
                $row = [
                    $submission->id,
                    $submission->user ? $submission->user->name : 'Anonymous',
                    $submission->created_at->format('Y-m-d H:i:s'),
                ];

                $values = $submission->values->keyBy('form_field_id');

                foreach ($fieldIds as $fieldId) {
                    $value = $values->get($fieldId);
                    if ($value) {
                        $displayValue = match ($value->field->type) {
                            'file' => $value->value ? route('submissions.download', ['submission' => $submission->id, 'filename' => basename($value->value)]) : '',
                            'checkbox' => $value->value,
                            default => $value->value,
                        };
                        $row[] = $displayValue;
                    } else {
                        $row[] = '';
                    }
                }

                fputcsv($file, $row);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $form->title . '-submissions.csv"',
        ]);
    }

    /**
     * Export user's own submissions to CSV
     */
    public function exportUserSubmissionsCsv(): StreamedResponse
    {
        $user = auth()->user();

        if (!$user) {
            abort(403);
        }

        $submissions = Submission::where('user_id', $user->id)
            ->with(['form', 'values', 'values.field'])
            ->latest()
            ->get();

        $callback = function() use ($submissions) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Submission ID', 'Form Title', 'Submitted At', 'Status']);

            foreach ($submissions as $submission) {
                fputcsv($file, [
                    $submission->id,
                    $submission->form->title,
                    $submission->created_at->format('Y-m-d H:i:s'),
                    'Submitted'
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="my-submissions.csv"',
        ]);
    }

    /**
     * Export single submission to JSON
     *
     * @throws AuthorizationException
     */
    public function exportSubmissionJson(Form $form, Submission $submission): JsonResponse
    {
        // Ensure the submission belongs to the form
        if ($submission->form_id !== $form->id) {
            abort(404, 'Submission does not belong to this form');
        }
        
        $this->authorize('export', $submission);

        try {
            // Load the submission with its values using eager loading
            $submission->load(['values', 'values.field', 'user', 'form.categories.fields']);

            // Prepare submission data for JSON export
            $submissionData = $this->prepareSubmissionDataForJson($submission);

            // Validate filename for security
            $filename = 'submission-' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $submission->id) . '.json';

            return response()->json($submissionData, 200, [
                'Content-Type' => 'application/json',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
            
        } catch (\Exception $e) {
            \Log::error('JSON export failed for submission', [
                'submission_id' => $submission->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            abort(500, 'Export failed. Please try again.');
        }
    }

    /**
     * Export all submissions for a form to JSON
     *
     * @throws AuthorizationException
     */
    public function exportFormJson(Form $form): StreamedResponse
    {
        $this->authorize('exportAllSubmissions', $form);

        // Load the form with its categories and fields
        $form->load([
            'categories' => function ($query) {
                $query->orderBy('order');
            },
            'categories.fields' => function ($query) {
                $query->orderBy('order');
            },
        ]);

        $callback = function() use ($form) {
            $output = fopen('php://output', 'w');
            
            // Start JSON structure
            fwrite($output, '{"form":');
            fwrite($output, json_encode([
                'id' => $form->id,
                'title' => $form->title,
                'description' => $form->description,
                'exported_at' => now()->toIso8601String(),
            ]));
            fwrite($output, ',"submissions":[');

            $first = true;
            
            // Process submissions in chunks to avoid memory issues
            $form->submissions()
                ->with(['values', 'values.field', 'user'])
                ->latest()
                ->chunk(100, function ($submissions) use ($output, &$first) {
                    foreach ($submissions as $submission) {
                        if (!$first) {
                            fwrite($output, ',');
                        }
                        $first = false;
                        
                        $submissionData = $this->prepareSubmissionDataForJson($submission);
                        fwrite($output, json_encode($submissionData));
                    }
                });

            // Close JSON structure
            fwrite($output, ']}');
            fclose($output);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="' . $form->title . '-submissions.json"',
        ]);
    }

    private function prepareSubmissionData(Submission $submission): array
    {
        $submissionValues = $submission->values->keyBy('form_field_id');

        $categories = $submission->form->categories->map(function ($category) use ($submissionValues, $submission) {
            $fields = $category->fields->map(function ($field) use ($submissionValues, $submission) {
                $value = $submissionValues->get($field->id);

                $displayValue = null;
                if ($value) {
                    $displayValue = match ($field->type) {
                        'file' => $value->value ? route('submissions.download', ['submission' => $submission->id, 'filename' => basename($value->value)]) : null,
                        'checkbox' => $value->value,
                        'radio', 'select' => $value->value,
                        default => $value->value,
                    };
                }

                return [
                    'label' => $field->label,
                    'type' => $field->type,
                    'displayValue' => $displayValue,
                ];
            });

            return [
                'name' => $category->name,
                'description' => $category->description,
                'fields' => $fields,
            ];
        });

        return [
            'id' => $submission->id,
            'created_at' => $submission->created_at->format('Y-m-d H:i:s'),
            'categories' => $categories,
        ];
    }

    /**
     * Prepare submission data for JSON export
     * Following Laravel serialization best practices
     */
    private function prepareSubmissionDataForJson(Submission $submission): array
    {
        // Load form categories with proper eager loading to avoid N+1 queries
        $submission->form->loadMissing(['categories.fields']);
        $submissionValues = $submission->values->keyBy('form_field_id');

        $categories = $submission->form->categories->map(function ($category) use ($submissionValues) {
            $fields = $category->fields->map(function ($field) use ($submissionValues) {
                $value = $submissionValues->get($field->id);
                
                $fieldData = [
                    'id' => $field->id,
                    'label' => $field->label,
                    'type' => $field->type,
                    'required' => (bool) $field->required,
                    'value' => null,
                    'file_name' => null,
                ];

                if ($value) {
                    if ($field->type === 'file' && $value->value) {
                        // For files, only include the filename, not the full path
                        $fileName = basename($value->value);
                        $fieldData['file_name'] = $fileName;
                        $fieldData['value'] = $fileName;
                    } else {
                        // Cast boolean values properly
                        $fieldData['value'] = $field->type === 'checkbox' ? 
                            (bool) $value->value : $value->value;
                    }
                }

                return $fieldData;
            });

            return [
                'id' => $category->id,
                'name' => $category->name,
                'description' => $category->description,
                'order' => (int) $category->order,
                'fields' => $fields,
            ];
        });

        return [
            'id' => $submission->id,
            'status' => $submission->status,
            'submitted_by' => $submission->user ? [
                'id' => $submission->user->id,
                'name' => $submission->user->name,
                'email' => $submission->user->email,
            ] : null,
            'submitted_at' => $submission->created_at?->toIso8601String(),
            'last_updated' => $submission->updated_at?->toIso8601String(),
            // For backward compatibility, expose last_activity derived from updated_at
            'last_activity' => $submission->updated_at?->toIso8601String(),
            'status_metadata' => $submission->status_metadata,
            'categories' => $categories,
        ];
    }
}
