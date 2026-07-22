<?php

use App\Http\Middleware\ApiLogMiddleware;
use App\Http\Middleware\ApiTokenIPMiddleware;
use App\Http\Middleware\FormAccessMiddleware;
use App\Http\Middleware\RemoveServerHeaders;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Trust proxies (Traefik/reverse proxy) for correct HTTPS detection
        $middleware->trustProxies(at: '*');

        // Web middleware
        $middleware->web(append: [
            FormAccessMiddleware::class,
            RemoveServerHeaders::class,
        ]);

        // API middleware
        $middleware->alias([
            'api.token.ip' => ApiTokenIPMiddleware::class,
        ]);

        // Add API log middleware to the API group
        $middleware->api(append: [
            ApiLogMiddleware::class,
            RemoveServerHeaders::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Handle DecryptException on 2FA routes — stale sessions after APP_KEY change
        $exceptions->renderable(function (DecryptException $e, Request $request) {
            if ($request->is('two-factor-challenge*')) {
                return redirect()->route('login')->with('status', 'Your session has expired. Please log in again.');
            }
        });

        // API-specific exception handling
        $exceptions->renderable(function (Throwable $e, Request $request) {
            // Only apply to API requests
            if (! $request->is('api/*')) {
                return null;
            }

            // Handle common exceptions with appropriate status codes and messages
            if ($e instanceof AuthenticationException) {
                return response()->json([
                    'message' => 'Unauthenticated',
                ], 401);
            }

            if ($e instanceof ValidationException) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $e->validator->errors()->toArray(),
                ], 422);
            }

            if ($e instanceof ModelNotFoundException) {
                return response()->json([
                    'message' => 'Resource not found',
                ], 404);
            }

            if ($e instanceof NotFoundHttpException) {
                return response()->json([
                    'message' => 'Endpoint not found',
                ], 404);
            }

            if ($e instanceof AccessDeniedHttpException) {
                return response()->json([
                    'message' => 'Access denied',
                ], 403);
            }

            // For all other exceptions in production, return generic message
            if (! config('app.debug')) {
                return response()->json([
                    'message' => 'Server error',
                ], 500);
            }

            // In debug mode, include the exception details
            return response()->json([
                'message' => 'Server error',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], 500);
        });
    })->create();
