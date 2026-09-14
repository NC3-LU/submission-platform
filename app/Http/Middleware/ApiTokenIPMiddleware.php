<?php

namespace App\Http\Middleware;

use App\Models\ApiSetting;
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
        $bearerToken = $request->bearerToken();
        $authenticationKey = $this->authenticationRateLimitKey($request, $bearerToken);
        if (RateLimiter::tooManyAttempts($authenticationKey, $this->maximumAuthenticationAttempts())) {
            $retryAfter = RateLimiter::availableIn($authenticationKey);

            return response()->json([
                'message' => 'Too many authentication attempts. Please try again later.',
            ], 429, ['Retry-After' => (string) $retryAfter]);
        }

        $ipAuthenticationKey = $this->ipAuthenticationRateLimitKey($request);
        if (RateLimiter::tooManyAttempts($ipAuthenticationKey, $this->maximumIpAuthenticationAttempts())) {
            $retryAfter = RateLimiter::availableIn($ipAuthenticationKey);

            return response()->json([
                'message' => 'Too many authentication attempts. Please try again later.',
            ], 429, ['Retry-After' => (string) $retryAfter]);
        }

        if (! $bearerToken) {
            // Apply auth rate limiter for missing tokens to prevent brute force
            $this->hitRateLimiter($request, 'missing_token', null);

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
            $this->hitRateLimiter($request, 'invalid_token:'.$tokenFingerprint, $bearerToken);

            return response()->json(['message' => 'Invalid API token'], 401);
        }

        // Check if token is expired
        if ($token->isExpired()) {
            // Apply auth rate limiter for expired tokens
            $this->hitRateLimiter($request, 'expired_token:'.$token->id, $bearerToken);

            return response()->json(['message' => 'API token has expired'], 401);
        }

        // Check IP restrictions if any
        if (! $token->isValidIp($request->ip())) {
            // Apply auth rate limiter for IP restriction failures
            $this->hitRateLimiter($request, 'ip_restricted:'.$token->id, $bearerToken);

            return response()->json([
                'message' => 'Access denied from this IP address',
            ], 403);
        }

        if (! $this->routeDeclaresAbility($request)) {
            return response()->json([
                'message' => 'API endpoint permission is not configured',
            ], 403);
        }

        // Set token for access in controllers
        $request->attributes->set('api_token', $token);

        // A valid credential resets the failed-authentication bucket for this IP.
        RateLimiter::clear($authenticationKey);

        return $next($request);
    }

    /**
     * Track a failed authentication attempt for rate limiting.
     */
    private function hitRateLimiter(Request $request, string $reason, ?string $bearerToken): void
    {
        RateLimiter::hit($this->authenticationRateLimitKey($request, $bearerToken), 60);
        RateLimiter::hit($this->ipAuthenticationRateLimitKey($request), 60);

        // Store info about the failed attempt for auditing if needed
        $this->logFailedAttempt($request, $reason);
    }

    private function authenticationRateLimitKey(Request $request, ?string $bearerToken): string
    {
        $fingerprint = $bearerToken === null
            ? 'missing'
            : substr(hash('sha256', $bearerToken), 0, 16);

        return 'api-auth:'.$request->ip().':'.$fingerprint;
    }

    private function ipAuthenticationRateLimitKey(Request $request): string
    {
        return 'api-auth-ip:'.$request->ip();
    }

    private function maximumAuthenticationAttempts(): int
    {
        try {
            return max(1, (int) ApiSetting::get('rate_limit_auth_attempts', 5));
        } catch (\Throwable) {
            return 5;
        }
    }

    private function maximumIpAuthenticationAttempts(): int
    {
        return max(20, $this->maximumAuthenticationAttempts() * 10);
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

    private function routeDeclaresAbility(Request $request): bool
    {
        return collect($request->route()?->gatherMiddleware() ?? [])
            ->contains(fn (string $middleware): bool => str_starts_with($middleware, 'api.ability:'));
    }
}
