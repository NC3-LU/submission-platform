<?php

namespace App\Http\Middleware;

use App\Models\ApiLog;
use App\Models\ApiSetting;
use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ApiLogMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Record the start time for performance tracking
        $startTime = microtime(true);

        // Process the request
        $response = $next($request);

        // Only log API requests
        if (! $this->shouldLogRequest($request)) {
            return $response;
        }

        try {
            // Get token information if available
            $userId = null;
            $tokenId = null;
            $apiToken = null;

            // Try to get from attribute (set by ApiTokenIPMiddleware)
            if ($request->attributes->has('api_token')) {
                $apiToken = $request->attributes->get('api_token');
                $tokenId = $apiToken->id;
                $userId = $apiToken->user_id;
            } else {
                // Try to extract from bearer token
                $bearerToken = $request->bearerToken();
                if ($bearerToken) {
                    $tokenHash = hash('sha256', $bearerToken);
                    $apiToken = ApiToken::where('token', $tokenHash)->first();

                    if ($apiToken) {
                        $tokenId = $apiToken->id;
                        $userId = $apiToken->user_id;
                    }
                }
            }

            // Calculate execution time
            $executionTime = microtime(true) - $startTime;

            // Prepare log data
            $logData = [
                'user_id' => $userId,
                'token_id' => $tokenId,
                'method' => $request->method(),
                'endpoint' => $request->path(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
                'request_data' => $this->sanitizeRequestData($request),
                'response_code' => $response->getStatusCode(),
                'execution_time' => round($executionTime * 1000, 2), // in milliseconds
                'timestamp' => now()->toDateTimeString(),
            ];

            // Log to database if token is valid (for authenticated requests)
            if ($apiToken) {
                ApiLog::create([
                    'user_id' => $userId,
                    'token_id' => $tokenId,
                    'method' => $request->method(),
                    'endpoint' => $request->path(),
                    'ip_address' => $request->ip(),
                    'request_data' => $this->sanitizeRequestData($request),
                    'response_code' => $response->getStatusCode(),
                    'execution_time' => $executionTime,
                ]);
            }

            // Always log to file, even for unauthorized attempts
            Log::channel('api')->info('API Request', $logData);

        } catch (\Exception $e) {
            // Log the error but don't affect the response
            Log::error('API logging failed: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request_path' => $request->path(),
                'request_method' => $request->method(),
            ]);
        }

        return $response;
    }

    /**
     * Determine if the request should be logged.
     */
    private function shouldLogRequest(Request $request): bool
    {
        try {
            // Check if logging is enabled in settings
            $loggingEnabled = (bool) ApiSetting::get('api_logging_enabled');

            if (! $loggingEnabled) {
                return false;
            }
        } catch (\Exception $e) {
            // Default to enabled if database unavailable
            // Log error for debugging
            Log::warning('Failed to check API logging setting', ['error' => $e->getMessage()]);
        }

        // Only log API requests
        return str_starts_with($request->path(), 'api/');
    }

    /**
     * Sanitize request data to remove sensitive information.
     */
    private function sanitizeRequestData(Request $request): array
    {
        $data = $request->except(['password', 'token', 'secret', 'key', 'authorization']);

        // Remove any other sensitive fields that might be nested
        return $this->sanitizeNestedData($data);
    }

    /**
     * Recursively sanitize nested data structures.
     */
    private function sanitizeNestedData(array $data): array
    {
        $sensitiveFields = ['password', 'token', 'api_token', 'secret', 'key', 'authorization'];
        $sanitized = [];

        foreach ($data as $key => $value) {
            // Check if this key contains a sensitive term
            $isSensitive = false;
            foreach ($sensitiveFields as $field) {
                if (stripos($key, $field) !== false) {
                    $isSensitive = true;
                    break;
                }
            }

            if ($isSensitive) {
                $sanitized[$key] = '***REDACTED***';
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeNestedData($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }
}
