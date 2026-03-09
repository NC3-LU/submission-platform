<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Models\FormUser;
use App\Models\Submission;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubmissionController extends Controller
{
    use AuthorizesRequests;

    /**
     * @throws Exception
     */
    public function show(Form $form): View|Factory|Application
    {

        try {
            if ($form->status !== 'published') {
                \Log::info('Form not published', ['status' => $form->status]);
                abort(404);
            }

            // Get draft submission if exists
            $draftSubmission = null;
            if (auth()->check()) {
                $draftSubmission = Submission::where([
                    'form_id' => $form->id,
                    'user_id' => auth()->id(),
                ])->whereIn('status', ['draft', 'ongoing'])
                    ->first();
            }

            \Log::info('About to render view', [
                'view_exists' => view()->exists('submissions.create'),
                'draft_exists' => !is_null($draftSubmission)
            ]);

            return view('submissions.create', [
                'form' => $form,
                'draftSubmission' => $draftSubmission
            ]);
        } catch (Exception $e) {
            \Log::error('Error in show method', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function store(Request $request, Form $form): RedirectResponse
    {
        if ($form->status !== 'published') {
            abort(404);
        }

        $categories = $form->categories()->with('fields')->get();

        $rules = [];
        foreach ($categories as $category) {
            foreach ($category->fields as $field) {
                $rule = [];
                if ($field->required) {
                    $rule[] = 'required';
                } else {
                    $rule[] = 'nullable';
                }

                if ($field->type === 'file') {
                    $rule[] = 'file';
                    $rule[] = 'max:10240'; // 10MB max file size
                    $rule[] = 'mimes:jpeg,png,pdf,doc,docx,xls,xlsx'; // Allowed file types
                }

                $rules['field_' . $field->id] = $rule;
            }
        }

        $validatedData = $request->validate($rules);

        $submission = $form->submissions()->create([
            'user_id' => auth()->id(), // This will be null for guest users
        ]);

        foreach ($categories as $category) {
            foreach ($category->fields as $field) {
                $value = $validatedData['field_' . $field->id] ?? null;

                if ($field->type === 'file' && $request->hasFile('field_' . $field->id)) {
                    $file = $request->file('field_' . $field->id);
                    $path = $file->store('submissions/' . $submission->id, 'private'); // Store in private storage
                    $value = $path;
                }

                $submission->values()->create([
                    'form_field_id' => $field->id,
                    'value' => $value,
                ]);
            }
        }

        return redirect()->route('submissions.thankyou')->with('success', 'Submission successful.');
    }

    /**
     * @throws AuthorizationException
     */
    public function edit(Form $form, Submission $submission): Factory|View|Application|RedirectResponse
    {
        $this->authorize('update', $submission);

        if (!in_array($submission->status, ['draft', 'ongoing'])) {
            return redirect()->route('submissions.show', ['form' => $form, 'submission' => $submission])
                ->with('error', 'This submission can no longer be edited.');
        }

        return view('submissions.edit', [
            'form' => $form,
            'submission' => $submission
        ]);
    }

    /**
     * Display a thank you page after submission.
     */
    public function thankyou(): View|Factory|Application
    {
        return view('submissions.thankyou');
    }

    /**
     * Display a listing of submissions for a form.
     * @throws AuthorizationException
     */
    public function index(Form $form): View|Factory|Application
    {
        $this->authorize('view', $form);
        return view('submissions.index', compact('form'));
    }

    /**
     * Display the specified submission.
     * @throws AuthorizationException
     */
    public function showSubmission(Form $form, Submission $submission): View
    {
        $this->authorize('view', $submission);

        // Ensure the submission belongs to the form
        if ($submission->form_id !== $form->id) {
            abort(404);
        }

        // Load the form with its categories and fields
        $form->load([
            'categories' => function ($query) {
                $query->orderBy('order');
            },
            'categories.fields' => function ($query) {
                $query->orderBy('order');
            },
        ]);

        // Load the submission values and their scan results
        $submission->load('values.scanResult', 'values.field');

        // Key the submission values by 'form_field_id' for easy access
        $submissionValues = $submission->values->keyBy('form_field_id');

        // Determine the back link based on user role
        $backLink = '';
        if (auth()->check()) {
            $userId = auth()->id();
            $isFormOwner = $form->user_id == $userId;
            $isFormUser = FormUser::where('form_id', $form->id)->where('user_id', $userId)->exists();
            $isSubmissionOwner = $submission->user_id == $userId;

            if ($isFormOwner || $isFormUser) {
                $backLink = route('submissions.index', $form);
            } elseif ($isSubmissionOwner) {
                $backLink = route('submissions.user');
            } else {
                $backLink = route('submissions.user'); // Default fallback
            }
        } else {
            $backLink = route('homepage'); // Fallback for guests
        }

        // Prepare categories with their fields and values
        $categories = $form->categories->map(function ($category) use ($submissionValues, $submission) {
            // Map over the category's fields
            $fields = $category->fields->map(function ($field) use ($submissionValues, $submission) {
                $value = $submissionValues->get($field->id);
                $scanResult = $value ? $value->scanResult : null;

                $displayValue = null;
                if ($value) {
                    $displayValue = match ($field->type) {
                        'file' => $value->value ? route('submissions.download', ['submission' => $submission->id, 'filename' => basename($value->value)]) : null,
                        'checkbox' => $value->value, // Show the actual selected options instead of just Yes/No
                        'radio', 'select' => $value->value,
                        default => $value->value,
                    };
                }

                // Return an array with field data and displayValue
                return [
                    'label' => $field->label,
                    'type' => $field->type,
                    'value' => $value ? $value->value : null,
                    'displayValue' => $displayValue,
                    'scanResult' => $scanResult,
                ];
            });

            // Return an array with category data and its fields
            return [
                'name' => $category->name,
                'description' => $category->description,
                'fields' => $fields,
            ];
        });

        return view('submissions.show', [
            'form' => $form,
            'submission' => $submission,
            'categories' => $categories,
            'backLink' => $backLink
        ]);
    }

    /**
     * Display a listing of submissions for the authenticated user.
     */
    public function showUserSubmission(): View|Factory|Application
    {
        $submissions = Submission::where('user_id', auth()->id())
            ->with(['form']) // Eager load relationships
            ->orderBy('updated_at', 'desc')
            ->paginate(10);

        return view('submissions.user-index', [
            'submissions' => $submissions
        ]);
    }

    /**
     * Delete a draft submission and its associated files.
     * @throws AuthorizationException
     */
    public function destroy(Submission $submission): RedirectResponse
    {
        $this->authorize('delete', $submission);

        // Only allow deletion of draft submissions
        if ($submission->status !== 'draft') {
            return redirect()->back()->with('error', 'Only draft submissions can be deleted.');
        }

        try {
            \DB::beginTransaction();

            // Get all file paths from submission values
            $filePaths = $submission->values()
                ->whereHas('field', function ($query) {
                    $query->where('type', 'file');
                })
                ->pluck('value')
                ->filter();

            // Delete all associated files from storage
            foreach ($filePaths as $path) {
                // Delete from both temporary and permanent locations
                Storage::disk('private')->delete($path);
                Storage::disk('private')->delete(str_replace('temp-submissions/', 'submissions/', $path));
            }

            // Delete the submission and its related values
            $submission->delete();

            \DB::commit();

            return redirect()->route('submissions.user')
                ->with('success', 'Submission deleted successfully.');

        } catch (Exception $e) {
            \DB::rollBack();
            logger()->error('Failed to delete submission', [
                'submission_id' => $submission->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->with('error', 'Failed to delete submission. Please try again.');
        }
    }

    /**
     * @throws AuthorizationException
     */
    public function downloadFile(Submission $submission, $filename): StreamedResponse
    {
        $this->authorize('generalPolicy', $submission);

        // Reject path traversal and null bytes
        if ($filename !== basename($filename) || str_contains($filename, "\0") || str_contains($filename, '..')) {
            abort(403, 'Invalid filename.');
        }

        // Determine the file path based on submission status
        $path = match ($submission->status) {
            'draft' => "temp-submissions/{$submission->id}/{$filename}",
            default => "submissions/{$submission->id}/{$filename}"
        };

        // Check if the file exists in private storage
        if (!Storage::disk('private')->exists($path)) {
            // If file not found in primary location and submission is draft/ongoing,
            // check the permanent location as fallback
            if (in_array($submission->status, ['draft'])) {
                $permanentPath = "submissions/{$submission->id}/{$filename}";
                if (Storage::disk('private')->exists($permanentPath)) {
                    return Storage::disk('private')->download($permanentPath);
                }
            }

            abort(404, 'File not found.');
        }

        // Serve the file securely
        return Storage::disk('private')->download($path);
    }

}
