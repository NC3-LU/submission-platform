<?php

namespace App\Livewire;

use App\Jobs\ScanSubmissionFileJob;
use App\Models\Form;
use App\Models\FormAccessLink;
use App\Models\Submission;
use App\Models\SubmissionValues;
use App\Services\FileCleanup;
use App\Services\SubmissionAnswers;
use Exception;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

class SubmissionForm extends Component
{
    use WithFileUploads;

    /**
     * The form being submitted
     */
    public Form $form;

    #[Locked]
    public string $requestKey;

    /**
     * The current submission instance
     */
    public ?Submission $submission = null;

    /**
     * Array of field values indexed by field ID
     *
     * @var array<int, mixed>
     */
    public array $fieldValues = [];

    /**
     * Array of temporary file uploads
     *
     * @var array<string, mixed>
     */
    public array $tempFiles = [];

    /**
     * Temp-disk paths this component wrote during this session, keyed by field.
     *
     * `fieldValues` round-trips through the browser, so it cannot be used to
     * decide what we are allowed to delete. This is #[Locked] and therefore
     * server-authoritative.
     *
     * @var array<int, string>
     */
    #[Locked]
    public array $uploadedTempPaths = [];

    /**
     * Current step in the multi-step form
     */
    public int $currentStep = 1;

    /**
     * Total number of steps in the form
     */
    public int $totalSteps;

    /**
     * Array of step data
     *
     * @var array<int, array{name: string, description: string}>
     */
    public array $steps = [];

    /**
     * Whether the form is in edit mode
     */
    public bool $isEditMode = false;

    /**
     * Auto-save interval in milliseconds
     */
    public int $autoSaveInterval = 30000;

    /**
     * Maximum file size in KB
     */
    protected const MAX_FILE_SIZE = 10240;

    /**
     * Allowed file types
     *
     * @var array<string>
     */
    protected const ALLOWED_FILE_TYPES = ['jpeg', 'jpg', 'webp', 'svg', 'png', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'md'];

    /**
     * Event listeners
     *
     * @var array<string>
     */
    protected $listeners = [
        'autosaveDraft',
    ];

    /**
     * Validation messages
     *
     * @var array<string, string>
     */
    protected $messages = [
        'fieldValues.*.required' => 'The :attribute field is required.',
        'tempFiles.*.max' => 'The file must not be larger than 10MB.',
        'tempFiles.*.mimes' => 'The file must be a valid document type (jpeg, png, pdf, doc, docx, xls, xlsx).',
    ];

    /**
     * Initialize the component
     */
    public function mount(Form $form, ?Submission $submission = null, bool $isEditMode = false): void
    {
        $this->form = $form->load([
            'categories' => fn ($query) => $query->orderBy('order'),
            'categories.fields' => fn ($query) => $query->orderBy('order'),
        ]);

        $this->requestKey = (string) Str::uuid();
        $this->totalSteps = $this->form->categories->count();
        $this->isEditMode = $isEditMode;

        $this->steps = $this->form->categories->map(function ($category) {
            return [
                'name' => $category->name,
                'description' => $category->description,
            ];
        })->toArray();

        if ($submission?->exists) {
            abort_unless($submission->form_id === $form->id && auth()->check() && auth()->user()->can('update', $submission), 403);
            $this->submission = $submission;
            $this->loadSubmissionValues();
        } else {
            $this->loadOrCreateDraft();
        }

        // We don't need to process checkbox values here as it would convert arrays back to strings
        // which breaks the UI state for checkboxes
    }

    /**
     * Get data for the current step
     *
     * @return array{name: string, description: string}
     */
    public function getCurrentStepDataProperty(): array
    {
        return $this->steps[$this->currentStep - 1] ?? [
            'name' => 'Step '.$this->currentStep,
            'description' => '',
        ];
    }

    /**
     * Get field labels for validation
     *
     * @return array<string, string>
     */
    protected function fieldLabels(): array
    {
        $labels = [];
        foreach ($this->form->categories as $category) {
            foreach ($category->fields as $field) {
                $labels["fieldValues.{$field->id}"] = $field->label;
                $labels["tempFiles.field_{$field->id}"] = $field->label;
            }
        }

        return $labels;
    }

    /**
     * Load values from an existing submission
     */
    protected function loadSubmissionValues(): void
    {
        if (! $this->submission) {
            return;
        }

        $this->submission->load('values');

        foreach ($this->submission->values as $value) {
            $field = null;

            // Find the field across all categories
            foreach ($this->form->categories as $category) {
                $foundField = $category->fields->firstWhere('id', $value->form_field_id);
                if ($foundField) {
                    $field = $foundField;
                    break;
                }
            }

            if (! $field) {
                // Field not found, skip this value
                Log::warning('Field not found when loading submission values', [
                    'form_field_id' => $value->form_field_id,
                    'submission_id' => $this->submission->id,
                ]);

                continue;
            }

            if ($field->type === 'checkbox') {

                // Convert stored comma-separated values back to array format for checkboxes
                $options = array_map('trim', explode(',', $field->options));

                // Handle empty values
                if (empty($value->value)) {
                    $this->fieldValues[$field->id] = array_fill(0, count($options), false);

                    continue;
                }

                $selectedValues = array_map('trim', explode(',', $value->value));

                $this->fieldValues[$field->id] = [];

                foreach ($options as $index => $option) {
                    $this->fieldValues[$field->id][$index] = in_array($option, $selectedValues);
                }

            } else {
                $this->fieldValues[$value->form_field_id] = $value->value;
            }
        }
    }

    /**
     * Load existing draft or create new one
     */
    protected function loadOrCreateDraft(): void
    {
        if (! auth()->check()) {
            return;
        }

        $this->submission = Submission::where([
            'form_id' => $this->form->id,
            'user_id' => auth()->id(),
        ])->whereIn('status', ['draft', 'ongoing'])
            ->orderBy('updated_at', 'desc')
            ->first();

        if ($this->submission) {
            $this->loadSubmissionValues();
        }

        // No draft row is created here on purpose. Merely opening a form must not
        // write to the database, otherwise every page view pollutes the submission
        // list and exports with an empty draft. The row is created lazily by
        // saveDraft() once the user has actually entered something.
    }

    /**
     * Whether the user has entered anything worth persisting.
     *
     * Guards lazy draft creation: an untouched form must never produce a row.
     */
    protected function hasEnteredContent(): bool
    {
        foreach ($this->fieldValues as $value) {
            if (is_array($value)) {
                // Checkbox groups arrive as [optionIndex => bool]; any truthy entry counts.
                if (array_filter($value)) {
                    return true;
                }

                continue;
            }

            if (is_string($value) ? trim($value) !== '' : $value !== null) {
                return true;
            }
        }

        return ! empty($this->tempFiles);
    }

    /**
     * Handle file upload updates
     */
    public function updatedTempFiles($value, $key): void
    {
        if (! $this->authorizeMutation()) {
            return;
        }
        $quotaKey = 'upload:'.$this->form->id.':'.(auth()->id() ?? request()->ip());
        if (RateLimiter::tooManyAttempts($quotaKey, max(1, config('submissions.uploads_per_minute')))) {
            $this->addError('quota', 'You have reached the upload limit. Please wait a minute before trying again.');

            return;
        }
        RateLimiter::hit($quotaKey, 60);
        $fieldId = str_replace('field_', '', $key);
        abort_unless($this->form->fields()->whereKey($fieldId)->where('type', 'file')->exists(), 403);

        try {
            // Validate BEFORE the file is written to disk.
            //
            // This used to run only at submit(), which made the allowlist
            // useless: the upload was stored here first and tempFiles[$key] was
            // then set to null, so the later "nullable|file|mimes|max" rules
            // validated a null and passed. Any file type, up to Livewire's
            // default 12MB, reached permanent storage.
            $this->validateOnly("tempFiles.{$key}", [
                "tempFiles.{$key}" => [
                    'file',
                    'max:'.self::MAX_FILE_SIZE,
                    'mimes:'.implode(',', self::ALLOWED_FILE_TYPES),
                ],
            ], $this->messages, $this->fieldLabels());

            $path = $value->store('temp-submissions', 'private');
            $this->fieldValues[$fieldId] = $path;
            $this->uploadedTempPaths[(int) $fieldId] = $path;

            if (array_key_exists($key, $this->tempFiles)) {
                $this->tempFiles[$key] = null;
            }

            $this->dispatch('success', 'File uploaded successfully');
        } catch (ValidationException $e) {
            // Drop the rejected upload so it cannot be picked up later.
            if (array_key_exists($key, $this->tempFiles)) {
                $this->tempFiles[$key] = null;
            }
            unset($this->fieldValues[$fieldId], $this->uploadedTempPaths[(int) $fieldId]);

            Log::error('Livewire file validation failed during upload for key: '.$key, [
                'error' => $e->getMessage(),
                'errors' => $e->errors(),
                'field_id' => $fieldId,
            ]);
            // Propagate errors to Livewire's error bag if not already there.
            foreach ($e->errors() as $errorKey => $messages) {
                foreach ($messages as $message) {
                    $this->addError($errorKey, $message);
                }
            }
        } catch (Exception $e) {
            Log::error('File upload processing failed in updatedTempFiles for key: '.$key, [
                'error' => $e->getMessage(),
                'field_id' => $fieldId,
                'trace' => $e->getTraceAsString(), // Full trace can be verbose, but useful for debugging
            ]);
            // Use $key for addError as it matches the wire:model target, e.g., "tempFiles.field_117"
            $this->addError($key, 'Failed to upload or process file. Please try again. If the issue persists, contact support.');
        }
    }

    /**
     * Delete uploaded file
     */
    public function deleteFile(int $fieldId): void
    {
        if (! $this->authorizeMutation()) {
            return;
        }
        $path = $this->fieldValues[$fieldId] ?? null;

        if (! is_string($path) || $path === '') {
            return;
        }

        if (! $this->ownsUploadedFile($fieldId, $path)) {
            // The path came off the wire and does not belong to this form's
            // upload for this field. Refuse rather than hand the caller an
            // arbitrary delete on the private disk.
            Log::warning('Refused to delete a file the submission form does not own', [
                'form_id' => $this->form->id,
                'submission_id' => $this->submission?->id,
                'field_id' => $fieldId,
                'path' => $path,
            ]);

            $this->dispatch('error', 'Failed to delete file.');

            return;
        }

        try {
            DB::transaction(function () use ($fieldId, $path) {
                if (! $this->authorizeMutation(true)) {
                    return;
                }
                $this->submission?->values()->where('form_field_id', $fieldId)->where('value', $path)->delete();
                FileCleanup::schedule('private', [$path]);
            });
            unset($this->fieldValues[$fieldId], $this->uploadedTempPaths[$fieldId]);
            $this->dispatch('success', 'File deleted successfully');
        } catch (Exception $e) {
            Log::error('File deletion failed', [
                'error' => $e->getMessage(),
                'field_id' => $fieldId,
            ]);
            $this->dispatch('error', 'Failed to delete file: '.$e->getMessage());
        }
    }

    /**
     * Whether $path is a file this component is entitled to delete: either one
     * it stored during this session, or one already recorded against this
     * submission for this field.
     */
    protected function ownsUploadedFile(int $fieldId, string $path): bool
    {
        if (! $this->form->fields()->where('id', $fieldId)->where('type', 'file')->exists()) {
            return false;
        }

        if (($this->uploadedTempPaths[$fieldId] ?? null) === $path) {
            return true;
        }

        return $this->submission !== null
            && $this->submission->ownsFilePath($path)
            && $this->submission->values()
                ->where('form_field_id', $fieldId)
                ->where('value', $path)
                ->exists();
    }

    /**
     * Auto-save draft
     *
     * @throws Exception
     */
    public function autosaveDraft(): void
    {
        if (! auth()->check()) {
            return;
        }

        // Silent by design: an unprompted toast every interval reads as a bug to users.
        $this->saveDraft(false);
    }

    /**
     * Save as draft
     *
     * @throws Exception
     */
    public function saveAsDraft(): void
    {
        if (! auth()->check()) {
            return;
        }
        $this->saveDraft(true);
    }

    /**
     * Save the current submission as a draft
     *
     * @param  bool  $showNotification  Whether to show a success notification
     */
    protected function saveDraft(bool $showNotification = true): void
    {
        if (! auth()->check() || (! $this->submission && ! $this->hasEnteredContent())) {
            return;
        }
        $beforeValues = $this->fieldValues;
        $beforeUploads = $this->uploadedTempPaths;
        try {
            DB::transaction(function () {
                if (! $this->authorizeMutation(true)) {
                    return;
                }
                $this->validate($this->rules(true), $this->messages, $this->fieldLabels());
                $this->submission ??= Submission::firstOrCreate([
                    'form_id' => $this->form->id,
                    'user_id' => auth()->id(),
                    'status' => 'draft',
                ]);
                $this->persistAnswers();
                $this->submission->touch();
            });
            if ($showNotification && ! $this->getErrorBag()->has('availability')) {
                $this->dispatch('success', 'Draft saved');
            }
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            foreach ($this->fieldValues as $fieldId => $path) {
                if (is_string($path) && $path !== ($beforeValues[$fieldId] ?? null) && $this->submission?->ownsFilePath($path) && ! SubmissionValues::where('value', $path)->exists()) {
                    FileCleanup::schedule('private', [$path]);
                }
            }
            $this->fieldValues = $beforeValues;
            $this->uploadedTempPaths = $beforeUploads;
            $this->submission = $this->submission?->fresh();
            report($e);
            $this->addError('save', 'Your draft could not be saved. Your answers are still on this page. Please try again.');
        }
    }

    /** Reload access and status for every write; the form lock serializes overlapping draft writes. */
    protected function authorizeMutation(bool $lock = false): bool
    {
        $query = Form::whereKey($this->form->id);
        $form = ($lock ? $query->lockForUpdate() : $query)->first();
        $access = $form && $form->canAccess(auth()->user());
        if ($form && ! $access && $form->visibility === 'private') {
            $grant = session('form_access_'.$form->id);
            $link = is_array($grant) && is_string($grant['token'] ?? null)
                ? FormAccessLink::findValidByToken($grant['token']) : null;
            $access = $link && $link->form_id === $form->id
                && (! isset($grant['expires_at']) || $grant['expires_at'] >= now()->timestamp);
        }
        if (! $form || $form->status !== 'published' || ! $form->isWithinAvailabilityWindow() || ! $access) {
            $this->addError('availability', 'This form is no longer available to you. Your answers have not been submitted.');
            $this->dispatch('error', 'This form is not accepting submissions.');

            return false;
        }
        if ($this->submission) {
            $submission = Submission::whereKey($this->submission->id)->lockForUpdate()->first();
            if (! $submission || $submission->form_id !== $form->id || ! auth()->check()
                || $submission->user_id !== auth()->id() || ! in_array($submission->status, ['draft', 'ongoing'], true)) {
                $this->addError('availability', 'This response can no longer be edited. Reload the page to see its current status.');

                return false;
            }
            $this->submission = $submission;
        }
        $this->form = $form->load('categories.fields', 'fields');
        $this->ownFieldIdCache = null;
        $this->ownFieldTypeCache = null;
        $this->resetErrorBag('availability');

        return true;
    }

    protected function persistAnswers(): void
    {
        $answers = app(SubmissionAnswers::class);
        $existingValues = $this->submission->values()->get()->keyBy('form_field_id');
        foreach ($this->form->fields as $field) {
            $previous = $existingValues->get($field->id);
            if (in_array($field->type, ['header', 'description']) || ! $answers->visible($field, $this->fieldValues, $this->form)) {
                if ($previous && $field->type === 'file') {
                    FileCleanup::schedule('private', $this->submission->ownedFilePathVariants((string) $previous->value));
                }
                $previous?->delete();

                continue;
            }
            $value = $this->fieldValues[$field->id] ?? null;
            if (! $this->canPersistFieldValue($field->id, $value)) {
                continue;
            }
            if ($previous && $field->type === 'file' && $previous->value !== $value) {
                FileCleanup::schedule('private', $this->submission->ownedFilePathVariants((string) $previous->value));
                $previous->delete();
            }
            $this->submission->values()->updateOrCreate(
                ['form_field_id' => $field->id], ['value' => $answers->storedValue($field, $value)]
            );
        }
        $this->handleFileUploads();
    }

    /**
     * Ids of the fields that belong to the form being submitted.
     *
     * @var array<int, int>|null
     */
    protected ?array $ownFieldIdCache = null;

    /** @var array<int, string>|null */
    protected ?array $ownFieldTypeCache = null;

    /**
     * Whether a client-supplied field id belongs to this form.
     *
     * `fieldValues` round-trips through the browser, so its keys are untrusted:
     * without this, answers (and files) could be attached to another form's
     * fields.
     */
    protected function ownsField(int|string $fieldId): bool
    {
        $this->ownFieldIdCache ??= $this->form->fields()->pluck('id')->all();

        return in_array((int) $fieldId, $this->ownFieldIdCache, true);
    }

    /**
     * File paths round-trip through the browser and must remain
     * server-authoritative. Only paths uploaded by this component or already
     * attached to the current submission may be persisted.
     */
    protected function canPersistFieldValue(int|string $fieldId, mixed $value): bool
    {
        if (! $this->ownsField($fieldId)) {
            return false;
        }

        $this->ownFieldTypeCache ??= $this->form->fields()->pluck('type', 'id')->all();

        if (($this->ownFieldTypeCache[(int) $fieldId] ?? null) !== 'file') {
            return true;
        }

        return is_string($value) && $this->ownsUploadedFile((int) $fieldId, $value);
    }

    /**
     * Move to next step
     */
    public function nextStep(): void
    {
        if ($this->currentStep < $this->totalSteps) {
            $this->currentStep++;
        }
    }

    /**
     * Move to previous step
     */
    public function previousStep(): void
    {
        if ($this->currentStep > 1) {
            $this->currentStep--;
        }
    }

    /**
     * Submit the form
     */
    public function submit(): void
    {
        $beforeValues = $this->fieldValues;
        $beforeUploads = $this->uploadedTempPaths;
        try {
            $saved = DB::transaction(function () {
                if (! $this->authorizeMutation(true)) {
                    return false;
                }
                $this->validate($this->rules(), $this->messages, $this->fieldLabels());
                $existing = Submission::where('request_key', $this->requestKey)->first();
                if ($existing) {
                    $this->submission = $existing;

                    return true;
                }
                $quotaKey = 'submission:'.$this->form->id.':'.(auth()->check() ? 'user:'.auth()->id() : 'ip:'.request()->ip());
                foreach (['minute' => 60, 'day' => 86400] as $period => $seconds) {
                    if (RateLimiter::tooManyAttempts($quotaKey.':'.$period, max(1, config('submissions.per_'.$period)))) {
                        $this->addError('quota', 'You have reached the submission limit. Please try again later.');

                        return false;
                    }
                }
                if (! $this->submission) {
                    $this->submission = Submission::create([
                        'request_key' => $this->requestKey,
                        'form_id' => $this->form->id,
                        'user_id' => auth()->id(),
                        'status' => 'submitted',
                    ]);
                } else {
                    $this->submission->update(['status' => 'submitted', 'request_key' => $this->requestKey]);
                }
                $this->persistAnswers();
                RateLimiter::hit($quotaKey.':minute', 60);
                RateLimiter::hit($quotaKey.':day', 86400);

                return true;
            });
            if ($saved) {
                session()->put('submission_receipt', $this->submission->id);
                $this->redirect(route('submissions.thankyou'));
            }
        } catch (ValidationException $e) {
            $first = array_key_first($e->errors());
            foreach ($this->form->categories as $index => $category) {
                foreach ($category->fields as $field) {
                    if ($first === 'fieldValues.'.$field->id || $first === 'tempFiles.field_'.$field->id) {
                        $this->currentStep = $index + 1;
                        $this->dispatch('focus-field', id: 'field_'.$field->id);
                        break 2;
                    }
                }
            }
            throw $e;
        } catch (\Throwable $e) {
            foreach ($this->fieldValues as $fieldId => $path) {
                if (is_string($path) && $path !== ($beforeValues[$fieldId] ?? null) && $this->submission?->ownsFilePath($path) && ! SubmissionValues::where('value', $path)->exists()) {
                    FileCleanup::schedule('private', [$path]);
                }
            }
            $this->fieldValues = $beforeValues;
            $this->uploadedTempPaths = $beforeUploads;
            $this->submission = $this->submission?->fresh();
            report($e);
            $this->addError('save', 'Your response could not be submitted. Your answers are still on this page. Please try again.');
        }
    }

    /**
     * Handle permanent file storage after submission.
     *
     * Malware scanning is dispatched to a queued job (ScanSubmissionFileJob) so
     * it never blocks the request. The job runs after the surrounding DB
     * transaction commits and quarantines malicious/unscannable files.
     */
    protected function handleFileUploads(): void
    {
        foreach ($this->fieldValues as $fieldId => $value) {
            // Skip if value is not a string (e.g., arrays from checkboxes)
            if (! is_string($value)) {
                continue;
            }

            // Check if this field is actually a file type on THIS form and the
            // value looks like a temp path.
            $fieldModel = $this->form->fields()->find($fieldId);
            if (! $fieldModel || $fieldModel->type !== 'file') {
                continue;
            }

            if (str_starts_with($value, 'temp-submissions/') && ($this->uploadedTempPaths[(int) $fieldId] ?? null) === $value && $value === 'temp-submissions/'.basename($value)) {
                $newPath = "submissions/{$this->submission->id}/".basename($value);
                if (! Storage::disk('private')->copy($value, $newPath)) {
                    throw new \RuntimeException('Unable to store attachment.');
                }
                FileCleanup::schedule('private', [$value]);
                unset($this->uploadedTempPaths[(int) $fieldId]);

                // Update the submission value with the new path
                $submissionValue = $this->submission->values()->updateOrCreate(
                    ['form_field_id' => $fieldId],
                    ['value' => $newPath]
                );

                $this->fieldValues[$fieldId] = $newPath; // Update component state

                // Queue an asynchronous malware scan. afterCommit() ensures the
                // worker only picks it up once the file's row is committed.
                if (config('services.pandora.enabled', false)) {
                    Log::info('Queuing file scan after upload', [
                        'submission_id' => $this->submission->id,
                        'submission_value_id' => $submissionValue->id,
                        'path' => $newPath,
                    ]);

                    ScanSubmissionFileJob::dispatch($submissionValue)->afterCommit();
                }
            }
        }
    }

    /**
     * Get all validation rules
     *
     * @return array<string, string>
     */
    public function rules(bool $draft = false): array
    {
        $rules = app(SubmissionAnswers::class)->rules($this->form, $this->fieldValues, 'fieldValues', $draft);
        foreach ($this->form->fields->where('type', 'file') as $field) {
            $key = 'fieldValues.'.$field->id;
            if (! isset($rules[$key])) {
                continue;
            }
            $rules[$key] = [$field->required && ! $draft ? 'required' : 'nullable', 'string', function ($attribute, $value, $fail) use ($field) {
                if (! $this->ownsUploadedFile($field->id, $value)) {
                    $fail('Upload a valid file for '.$field->label.'.');
                }
            }];
        }

        return $rules;
    }

    /**
     * Render the component
     */
    public function render(): View|Factory|Application
    {
        return view('livewire.submission-form');
    }

    /**
     * Debug method to log checkbox state
     */
}
