<?php

use App\Http\Controllers\Api\ApiTokenController;
use App\Http\Controllers\Api\FormAccessController;
use App\Http\Controllers\Api\FormController;
use App\Http\Controllers\Api\SubmissionController;
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
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// API v1 Routes
Route::middleware(['api.token.ip', 'throttle:api'])
    ->prefix('v1')
    ->name('api.')
    ->group(function () {
        // API Token Management
        Route::post('tokens/{token}/rotate', [ApiTokenController::class, 'rotate'])
            ->name('tokens.rotate')
            ->middleware('api.ability:tokens:manage');

        Route::apiResource('tokens', ApiTokenController::class)
            ->except(['show'])
            ->middleware('api.ability:tokens:manage');

        // Form Management
        Route::apiResource('forms', FormController::class)
            ->middlewareFor(['index', 'show'], 'api.ability:forms:read')
            ->middlewareFor('store', 'api.ability:forms:create')
            ->middlewareFor('update', 'api.ability:forms:update')
            ->middlewareFor('destroy', 'api.ability:forms:delete');

        // Form Access Links
        Route::apiResource('form.access-links', FormAccessController::class)
            ->middlewareFor(['index', 'show'], 'api.ability:forms:read')
            ->middlewareFor(['store', 'update', 'destroy'], 'api.ability:forms:update');

        // Form Submissions (with specific rate limiting)
        Route::middleware(['throttle:api-submissions'])
            ->group(function () {
                Route::apiResource('forms.submissions', SubmissionController::class)
                    ->middlewareFor(['index', 'show'], 'api.ability:submissions:read')
                    ->middlewareFor('store', 'api.ability:submissions:create')
                    ->middlewareFor('update', 'api.ability:submissions:update')
                    ->middlewareFor('destroy', 'api.ability:submissions:delete');
            });
    });
