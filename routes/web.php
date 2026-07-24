<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FormAccessController;
use App\Http\Controllers\FormController;
use App\Http\Controllers\FormFieldController;
use App\Http\Controllers\SubmissionController;
use App\Http\Controllers\SubmissionExportController;
use App\Http\Middleware\FormAccessMiddleware;
use Illuminate\Support\Facades\Route;

// Public routes (no authentication required)
Route::get('/', [FormController::class, 'index'])
    ->name('homepage')
    ->middleware(FormAccessMiddleware::class);

Route::get('/forms', [FormController::class, 'publicIndex'])
    ->name('forms.public_index')
    ->middleware(FormAccessMiddleware::class);

// Form access link routes - public facing
Route::get('/forms/access/{token}', [FormAccessController::class, 'accessForm'])
    ->name('form.access')
    ->middleware(FormAccessMiddleware::class);

// Form submission routes - public facing but protected by FormAccessMiddleware
Route::get('/forms/{form}/submit', [SubmissionController::class, 'show'])->name('submissions.create');
Route::get('/thank-you', [SubmissionController::class, 'thankyou'])->name('submissions.thankyou');

// Authenticated routes
Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Form Management
    Route::prefix('forms')->name('forms.')->group(function () {
        Route::get('/my-forms', [FormController::class, 'userIndex'])->name('user_index');
        Route::get('/create', [FormController::class, 'create'])->name('create');
        Route::post('/', [FormController::class, 'store'])->name('store');
        Route::get('/{form}/edit', [FormController::class, 'edit'])->name('edit');
        Route::get('/{form}', [SubmissionController::class, 'show'])->name('show');
        Route::put('/{form}', [FormController::class, 'update'])->name('update');
        Route::delete('/{form}', [FormController::class, 'destroy'])->name('destroy');
        Route::get('/{form}/preview', [FormController::class, 'preview'])->name('preview');
        Route::post('/{form}/duplicate', [FormController::class, 'duplicate'])->name('duplicate');

        // Form Access Management - protected actions
        Route::post('/{form}/assign-users', [FormAccessController::class, 'assignUsers'])->name('assign-users');
        Route::post('/{form}/create-access-link', [FormAccessController::class, 'createAccessLink'])->name('create-access-link');
        Route::delete('/access-links/{accessLink}', [FormAccessController::class, 'deleteAccessLink'])->name('delete-access-link');

        // Form Field Management
        // scopeBindings() resolves {field} through $form->fields(), so a field
        // belonging to another form 404s. `can:update,form` only authorizes the
        // {form} segment — without the scoping, any form owner could edit or
        // delete fields on someone else's form.
        Route::prefix('{form}/fields')->name('fields.')->middleware(['can:update,form'])->scopeBindings()->group(function () {
            Route::post('/', [FormFieldController::class, 'store'])->name('store');
            Route::put('/{field}', [FormFieldController::class, 'update'])->name('update');
            Route::delete('/{field}', [FormFieldController::class, 'destroy'])->name('destroy');
        });

        // User Removal from Form
        Route::delete('/{form}/users/{user}', [FormController::class, 'removeUser'])->name('remove-user');
    });

    // Submission Management
    Route::prefix('submissions')->name('submissions.')->group(function () {
        Route::get('/my-submissions', [SubmissionController::class, 'showUserSubmission'])->name('user');
        Route::delete('/{submission}', [SubmissionController::class, 'destroy'])->name('destroy');
        Route::get('/{submission}/download/{filename}', [SubmissionController::class, 'downloadFile'])->name('download');

        // Form Submissions
        Route::prefix('forms/{form}')->group(function () {
            Route::get('/submissions', [SubmissionController::class, 'index'])
                ->middleware(['can:viewAny,App\Models\Submission,form'])
                ->name('index');

            Route::get('/submissions/{submission}', [SubmissionController::class, 'showSubmission'])->name('show');
            Route::get('/submissions/edit/{submission}', [SubmissionController::class, 'edit'])->name('edit');
        });
    });

    Route::get('forms/{form}/submissions/{submission}/export/pdf', [SubmissionExportController::class, 'exportSubmissionPdf'])
        ->middleware('throttle:export')
        ->name('submissions.export.single.pdf');

    Route::get('forms/{form}/submissions/{submission}/export/json', [SubmissionExportController::class, 'exportSubmissionJson'])
        ->middleware('throttle:export')
        ->name('submissions.export.single.json');

    Route::get('forms/{form}/export/json', [SubmissionExportController::class, 'exportFormJson'])
        ->middleware('throttle:bulk-export')
        ->name('submissions.export.form.json');

});
