<?php

use App\Http\Controllers\Api\ApiTokenController;
use App\Http\Controllers\Api\FormAccessController;
use App\Http\Controllers\Api\FormCollaboratorController;
use App\Http\Controllers\Api\FormController;
use App\Http\Controllers\Api\FormDuplicationController;
use App\Http\Controllers\Api\SubmissionController;
use App\Http\Controllers\Api\SubmissionExportController;
use App\Http\Controllers\Api\SubmissionFileController;
use App\Http\Controllers\Api\TokenContextController;
use App\Http\Controllers\Api\TokenEventController;
use App\Http\Controllers\Api\TokenRevocationController;
use App\Http\Controllers\Api\WebhookEndpointController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// User profile endpoint
Route::middleware(['api.deprecated:api.me.show', 'auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

// API v1 Routes
Route::middleware(['api.token.ip', 'throttle:api'])
    ->prefix('v1')
    ->name('api.')
    ->group(function () {
        Route::get('me', [TokenContextController::class, 'show'])->name('me.show')->defaults('api_permission', 'authenticated');
        Route::delete('me/token', [TokenContextController::class, 'destroy'])->name('me.destroy')->defaults('api_permission', 'authenticated');
        Route::get('token-events', [TokenEventController::class, 'index'])->name('token-events.index')->middleware('api.ability:tokens:manage');
        Route::post('token-revocations', [TokenRevocationController::class, 'store'])->name('token-revocations.store')
            ->middleware(['api.ability:tokens:manage', 'throttle:api-token-revocations']);

        // API Token Management
        Route::post('tokens/{token}/rotate', [ApiTokenController::class, 'rotate'])
            ->name('tokens.rotate')
            ->middleware('api.ability:tokens:manage');

        Route::apiResource('tokens', ApiTokenController::class)
            ->except(['show'])
            ->middleware('api.ability:tokens:manage');

        Route::post('forms/{form}/duplicate', [FormDuplicationController::class, 'store'])
            ->name('forms.duplicate')->middleware(['api.ability:forms:create', 'throttle:api-form-mutations']);

        Route::apiResource('forms.users', FormCollaboratorController::class)->except('show')
            ->whereNumber('user')->middleware('api.ability:forms:share')
            ->middlewareFor(['store', 'update', 'destroy'], 'throttle:api-form-mutations');

        Route::post('forms/{form}/exports', [SubmissionExportController::class, 'store'])
            ->name('forms.exports.store')->middleware(['api.ability:submissions:export', 'throttle:api-form-mutations']);
        Route::get('forms/{form}/exports/{export}', [SubmissionExportController::class, 'show'])
            ->scopeBindings()->name('forms.exports.show')->middleware('api.ability:submissions:export');
        Route::get('forms/{form}/exports/{export}/download', [SubmissionExportController::class, 'download'])
            ->scopeBindings()->name('forms.exports.download')->middleware(['api.ability:submissions:export', 'throttle:api-submissions']);

        Route::middleware(['api.ability:webhooks:manage'])->group(function () {
            Route::apiResource('webhook-endpoints', WebhookEndpointController::class)->parameters(['webhook-endpoints' => 'endpoint'])
                ->middlewareFor(['store', 'update', 'destroy'], 'throttle:api-webhooks');
            Route::get('webhook-endpoints/{endpoint}/deliveries', [WebhookEndpointController::class, 'deliveries'])->name('webhook-endpoints.deliveries');
            Route::post('webhook-endpoints/{endpoint}/rotate-secret', [WebhookEndpointController::class, 'rotate'])->name('webhook-endpoints.rotate')->middleware('throttle:api-webhooks');
            Route::post('webhook-endpoints/{endpoint}/test', [WebhookEndpointController::class, 'test'])->name('webhook-endpoints.test')->middleware('throttle:api-webhooks');
            Route::post('webhook-endpoints/{endpoint}/deliveries/{delivery}/redeliver', [WebhookEndpointController::class, 'redeliver'])->name('webhook-endpoints.redeliver')->middleware('throttle:api-webhooks');
        });

        // Form Management
        Route::apiResource('forms', FormController::class)
            ->middlewareFor(['index', 'show'], 'api.ability:forms:read')
            ->middlewareFor('store', 'api.ability:forms:create')
            ->middlewareFor('update', 'api.ability:forms:update')
            ->middlewareFor('destroy', 'api.ability:forms:delete');

        // Form Access Links
        Route::apiResource('forms.access-links', FormAccessController::class)->scoped()
            ->middlewareFor(['index', 'show'], 'api.ability:forms:read')
            ->middlewareFor(['store', 'update', 'destroy'], 'api.ability:forms:update');

        // Compatibility until 1 April 2027; named links and documentation use the plural routes.
        foreach ([
            'index' => [['GET'], '', 'forms:read'],
            'store' => [['POST'], '', 'forms:update'],
            'show' => [['GET'], '/{access_link}', 'forms:read'],
            'update' => [['PUT', 'PATCH'], '/{access_link}', 'forms:update'],
            'destroy' => [['DELETE'], '/{access_link}', 'forms:update'],
        ] as $action => [$methods, $suffix, $ability]) {
            Route::match($methods, 'form/{form}/access-links'.$suffix, [FormAccessController::class, $action])
                ->scopeBindings()->name('legacy.form-access-links.'.$action)
                ->middleware(['api.ability:'.$ability, 'api.deprecated:api.forms.access-links.'.$action]);
        }

        // Form Submissions (with specific rate limiting)
        Route::middleware(['throttle:api-submissions'])
            ->group(function () {
                Route::get('forms/{form}/submissions/{submission}/files/{value}', [SubmissionFileController::class, 'show'])
                    ->scopeBindings()->name('forms.submissions.files.show')->middleware('api.ability:submissions:read');
                Route::apiResource('forms.submissions', SubmissionController::class)
                    ->middlewareFor(['index', 'show'], 'api.ability:submissions:read')
                    ->middlewareFor('store', 'api.ability:submissions:create')
                    ->middlewareFor('update', 'api.ability:submissions:update')
                    ->middlewareFor('destroy', 'api.ability:submissions:delete');
            });
    });
