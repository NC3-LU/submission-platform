<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class ApiTokenIPMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get the bearer token from the request
        $bearerToken = $request->bearerToken();

        if (! $bearerToken) {
            // Apply auth rate limiter for missing tokens to prevent brute force
            $this->hitRateLimiter($request, 'missing_token');

            return response()->json(['message' => 'No API token provided'], 401);
        }

        // Generate a consistent token fingerprint (first 8 chars of hash) for rate limiting
        // This allows tracking specific tokens without revealing the full token
        $tokenFingerprint = substr(hash('sha256', $bearerToken), 0, 8);

        // Find the token in our database
        $tokenHash = hash('sha256', $bearerToken);
        $token = ApiToken::where('token', $tokenHash)->first();

        if (! $token) {
            // Apply auth rate limiter for invalid tokens
            $this->hitRateLimiter($request, 'invalid_token:'.$tokenFingerprint);

            return response()->json(['message' => 'Invalid API token'], 401);
        }

        // Check if token is expired
        if ($token->isExpired()) {
            // Apply auth rate limiter for expired tokens
            $this->hitRateLimiter($request, 'expired_token:'.$token->id);

            return response()->json(['message' => 'API token has expired'], 401);
        }

        // Check IP restrictions if any
        if (! $token->isValidIp($request->ip())) {
            // Apply auth rate limiter for IP restriction failures
            $this->hitRateLimiter($request, 'ip_restricted:'.$token->id);

            return response()->json([
                'message' => 'Access denied from this IP address',
            ], 403);
        }

        // Update last used timestamp
        $token->markAsUsed();

        // Increment token usage count (if column exists)
        if (in_array('usage_count', $token->getFillable())) {
            $token->increment('usage_count');
        }

        // Check token abilities based on the request
        $requiredAbility = $this->getRequiredAbility($request);
        if ($requiredAbility && ! $token->can($requiredAbility)) {
            // Apply auth rate limiter for permission failures
            $this->hitRateLimiter($request, 'permission_denied:'.$token->id.':'.$requiredAbility);

            return response()->json([
                'message' => 'API token does not have the required permissions',
            ], 403);
        }

        // Set token for access in controllers
        $request->attributes->set('api_token', $token);

        // Clear any rate limiting locks for this token as it's now successful
        RateLimiter::clear('api-auth:success:'.$token->id);

        return $next($request);
    }

    /**
     * Track a failed authentication attempt for rate limiting.
     */
    private function hitRateLimiter(Request $request, string $key): void
    {
        // Ensure the rate limiter for API authentication is triggered
        // The actual limits are defined in AppServiceProvider
        RateLimiter::hit('api-auth:'.$key.':'.$request->ip());

        // Also track in general auth bucket
        RateLimiter::hit('api-auth:'.$request->ip());

        // Store info about the failed attempt for auditing if needed
        $this->logFailedAttempt($request, $key);
    }

    /**
     * Log information about a failed authentication attempt.
     */
    private function logFailedAttempt(Request $request, string $reason): void
    {
        // Store failed attempt info in cache for a short time
        // This could be expanded to write to database for serious security incidents
        $cacheKey = 'api:failed_auth:'.$request->ip();
        $attempts = Cache::get($cacheKey, []);

        $attempts[] = [
            'timestamp' => now()->toIso8601String(),
            'ip' => $request->ip(),
            'user_agent' => $request->header('User-Agent'),
            'reason' => $reason,
            'endpoint' => $request->path(),
        ];

        // Keep only the last 10 attempts to prevent cache bloat
        if (count($attempts) > 10) {
            array_shift($attempts);
        }

        // Store for 1 hour
        Cache::put($cacheKey, $attempts, now()->addHour());
    }

    /**
     * Determine the required ability for the request.
     */
    private function getRequiredAbility(Request $request): ?string
    {
        $path = $request->path();
        $method = $request->method();

        $verb = match ($method) {
            'GET', 'HEAD' => 'read',
            'POST' => 'create',
            'PUT', 'PATCH' => 'update',
            'DELETE' => 'delete',
            default => null,
        };

        if ($verb === null) {
            return null;
        }

        // Order matters: submissions and access-links are nested under
        // `forms/{id}/`, so they have to be matched before the broader forms
        // pattern or they resolve to a forms:* ability and the narrower
        // abilities become unreachable.
        if (preg_match('#^api/v1/forms/[^/]+/submissions#', $path)) {
            return 'submissions:'.$verb;
        }

        // Access links are form configuration, so they ride on forms:*.
        if (preg_match('#^api/v1/forms?/[^/]+/access-links#', $path)) {
            return $verb === 'read' ? 'forms:read' : 'forms:update';
        }

        if (preg_match('#^api/v1/forms(/|$)#', $path)) {
            return 'forms:'.$verb;
        }

        // Token management is a privileged operation in its own right: without
        // this, any token could mint an unrestricted one for its user.
        if (preg_match('#^api/v1/tokens(/|$)#', $path)) {
            return 'tokens:manage';
        }

        // Default to null (no specific ability required)
        return null;
    }
}
