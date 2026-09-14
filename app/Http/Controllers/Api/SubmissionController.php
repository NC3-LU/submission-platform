<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SubmissionResource;
use App\Jobs\ScanSubmissionFileJob;
use App\Models\ApiToken;
use App\Models\Form;
use App\Models\Submission;
use App\Models\SubmissionValues;
use App\Services\SubmissionAnswers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class SubmissionController extends Controller
{
    /**
     * Display a listing of submissions for a form.
     *
     * @return AnonymousResourceCollection|JsonResponse
     */
    public function index(Request $request, Form $form)
    {
        $apiToken = ApiToken::fromRequest($request);
        $userId = $apiToken->user_id;

        Gate::forUser($apiToken->user)->authorize('viewAny', [Submission::class, $form]);

        $filters = $request->validate([
            'status' => 'sometimes|string|in:draft,ongoing,submitted,under_review,approved,rejected,processing,completed',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $query = $form->submissions()->visibleTo($apiToken->user);

        // Apply filters if provided
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['start_date'])) {
            $query->whereDate('created_at', '>=', $filters['start_date']);
        }

        if (isset($filters['end_date'])) {
            $query->whereDate('created_at', '<=', $filters['end_date']);
        }

        $submissions = $query->latest()->paginate($filters['per_page'] ?? 15);

        // Load values relationship for each submission
        $submissions->load('values.field');

        return SubmissionResource::collection($submissions);
    }

    /**
     * Store a newly created submission.
     */
    public function store(Request $request, Form $form): JsonResponse
    {
        $apiToken = ApiToken::fromRequest($request);
        $userId = $apiToken->user_id;

        // Check if form is published
        if ($form->status !== 'published') {
            return response()->json(['message' => 'Form is not available for submissions'], 403);
        }

        if (! $form->isWithinAvailabilityWindow()) {
            return response()->json(['message' => 'Form is not available for submissions'], 403);
        }

        // For API-based submissions, we need to validate against the API token
        // rather than the authenticated user
        if (! $form->canAccess($apiToken->user)) {
            return response()->json(['message' => 'Unauthorized access to this form'], 403);
        }

        // Get all form fields for validation
        $formFields = $form->fields()->with('category')->get();

        $request->validate(['values' => 'sometimes|array']);
        $answers = app(SubmissionAnswers::class);
        $form->setRelation('fields', $formFields);
        $rules = $answers->rules($form, $request->input('values', []));

        // Validate the submission
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $values = $validator->validated()['values'] ?? [];
        $ownFieldIds = $formFields->pluck('id')->all();

        try {
            // Use a transaction to ensure data integrity
            return DB::transaction(function () use ($request, $form, $values, $ownFieldIds, $answers) {
                $form = Form::whereKey($form->id)->lockForUpdate()->firstOrFail();
                abort_unless($form->status === 'published' && $form->isWithinAvailabilityWindow() && $form->canAccess(ApiToken::fromRequest($request)->user), 403);
                $values = Validator::make($request->all(), $answers->rules($form, $request->input('values', [])))->validate()['values'] ?? [];
                $ownFieldIds = $form->fields->pluck('id')->all();
                // Create the submission
                $submission = Submission::create([
                    'form_id' => $form->id,
                    'ip_address' => $request->ip(),
                    'status' => 'submitted',
                ]);

                // Create submission values.
                //
                // The keys of `values` are client-supplied field ids, so they
                // are matched against this form's fields — otherwise a caller
                // could attach answers to another form's fields.
                foreach ($values as $fieldId => $value) {
                    if (! in_array((int) $fieldId, $ownFieldIds, true)) {
                        continue;
                    }

                    $field = $form->fields->firstWhere('id', (int) $fieldId);
                    if (in_array($field->type, ['header', 'description']) || ! $answers->visible($field, $values, $form)) {
                        continue;
                    }
                    if ($value instanceof UploadedFile) {
                        $value = $value->store('submissions/'.$submission->id, 'private');
                    } else {
                        $value = $answers->storedValue($field, $value);
                    }
                    $savedValue = SubmissionValues::create([
                        'submission_id' => $submission->id,
                        'form_field_id' => $fieldId,
                        'value' => $value,
                    ]);
                    if ($field->type === 'file' && $value && config('services.pandora.enabled')) {
                        ScanSubmissionFileJob::dispatch($savedValue)->afterCommit();
                    }
                }

                // Load values for the response
                $submission->load('values.field');

                return response()->json([
                    'message' => 'Submission created successfully',
                    'data' => new SubmissionResource($submission),
                ], 201);
            });
        } catch (ValidationException|HttpExceptionInterface $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('API Submission creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $response = ['message' => 'Submission creation failed'];

            // Only include error details in debug mode
            if (config('app.debug')) {
                $response['error'] = $e->getMessage();
            }

            return response()->json($response, 500);
        }
    }

    /**
     * Display the specified submission.
     *
     * @return SubmissionResource|JsonResponse
     */
    public function show(Request $request, Form $form, Submission $submission)
    {
        $apiToken = ApiToken::fromRequest($request);
        $userId = $apiToken->user_id;

        // Check if submission belongs to the specified form
        if ($submission->form_id !== $form->id) {
            return response()->json(['message' => 'Submission not found for this form'], 404);
        }

        Gate::forUser($apiToken->user)->authorize('view', $submission);

        // Load values relationship with fields
        $submission->load('values.field');

        return new SubmissionResource($submission);
    }

    /**
     * Update the submission status.
     *
     * @return SubmissionResource|JsonResponse
     */
    public function update(Request $request, Form $form, Submission $submission)
    {
        $apiToken = ApiToken::fromRequest($request);
        $userId = $apiToken->user_id;

        // Check if submission belongs to the specified form
        if ($submission->form_id !== $form->id) {
            return response()->json(['message' => 'Submission not found for this form'], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => ['required', Rule::in(Submission::REVIEW_STATUSES)],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::transaction(function () use ($apiToken, $submission, $request) {
            $submission = Submission::whereKey($submission->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($apiToken->user)->authorize('manageStatus', $submission);
            if (in_array($submission->status, Submission::EDITABLE_STATUSES)) {
                throw ValidationException::withMessages(['status' => 'Drafts must be submitted by their author.']);
            }
            $submission->update(['status' => $request->status]);
        });

        return new SubmissionResource($submission->refresh());
    }

    /**
     * Remove the specified submission.
     */
    public function destroy(Request $request, Form $form, Submission $submission): JsonResponse
    {
        $apiToken = ApiToken::fromRequest($request);
        $userId = $apiToken->user_id;

        // Check if submission belongs to the specified form
        if ($submission->form_id !== $form->id) {
            return response()->json(['message' => 'Submission not found for this form'], 404);
        }

        Gate::forUser($apiToken->user)->authorize('deleteAsEvaluator', $submission);

        // Delete submission and its values
        DB::transaction(function () use ($submission) {
            $submission->delete();
        });

        return response()->json(['message' => 'Submission deleted successfully'], 200);
    }
}
