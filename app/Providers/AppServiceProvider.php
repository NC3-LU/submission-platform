<?php

namespace App\Providers;

use App\Models\ApiSetting;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerGates();
        $this->registerRateLimiters();
        $this->loadDatabaseConfigs();
    }

    /**
     * Register application gates.
     */
    private function registerGates(): void
    {
        Gate::define('viewApiDocs', function (User $user) {
            return $this->isDomainAllowed($user->email);
        });
    }

    /**
     * Check if the user's email domain is allowed to access API docs.
     */
    private function isDomainAllowed(string $email): bool
    {
        $host = request()->getHost();

        // Allow all access if domain starts with "test."
        if (str_starts_with($host, 'test.')) {
            return true;
        }

        // Get allowed domains from database settings
        try {
            $allowedDomainsStr = ApiSetting::get('api_docs_allowed_domains', env('API_DOCS_ALLOWED_DOMAINS', ''));
        } catch (\Throwable $e) {
            $allowedDomainsStr = env('API_DOCS_ALLOWED_DOMAINS', '');
        }

        if (empty($allowedDomainsStr)) {
            return false;
        }

        $allowedDomains = array_filter(array_map('trim', explode(',', $allowedDomainsStr)));
        $emailDomain = substr(strrchr($email, '@'), 1);

        return ! empty($emailDomain) && in_array($emailDomain, $allowedDomains);
    }

    /**
     * Register application rate limiters.
     */
    private function registerRateLimiters(): void
    {
        RateLimiter::for('api', function (Request $request) {
            $apiToken = $request->attributes->get('api_token');

            try {
                if ($apiToken) {
                    $limit = max(1, (int) ApiSetting::get('rate_limit_api_authenticated', 60));

                    return Limit::perMinute($limit)->by('token:'.$apiToken->id);
                }

                $limit = max(1, (int) ApiSetting::get('rate_limit_api_unauthenticated', 30));

                return Limit::perMinute($limit)->by('ip:'.$request->ip());
            } catch (\Throwable $e) {
                $key = $apiToken ? 'token:'.$apiToken->id : 'ip:'.$request->ip();

                return Limit::perMinute(60)->by($key);
            }
        });

        RateLimiter::for('api-auth', function (Request $request) {
            try {
                $limit = max(1, (int) ApiSetting::get('rate_limit_auth_attempts', 5));

                return Limit::perMinute($limit)->by('ip:'.$request->ip());
            } catch (\Throwable $e) {
                return Limit::perMinute(5)->by('ip:'.$request->ip());
            }
        });

        RateLimiter::for('api-submissions', function (Request $request) {
            $apiToken = $request->attributes->get('api_token');
            $identifier = $apiToken?->id ?? $request->ip();

            try {
                if ($request->isMethod('GET')) {
                    $limit = max(1, (int) ApiSetting::get('rate_limit_submissions_read', 60));

                    return Limit::perMinute($limit)->by('token:'.$identifier);
                }

                $writeLimit = max(1, (int) ApiSetting::get('rate_limit_submissions_write', 30));
                $dailyLimit = max(1, (int) ApiSetting::get('rate_limit_submissions_daily', 1000));

                return [
                    Limit::perMinute($writeLimit)->by('token:'.$identifier),
                    Limit::perDay($dailyLimit)->by('daily:token:'.$identifier),
                ];
            } catch (\Throwable $e) {
                if ($request->isMethod('GET')) {
                    return Limit::perMinute(60)->by('token:'.$identifier);
                }

                return [
                    Limit::perMinute(30)->by('token:'.$identifier),
                    Limit::perDay(1000)->by('daily:token:'.$identifier),
                ];
            }
        });

        RateLimiter::for('export', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('bulk-export', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });
    }

    /**
     * Load configuration values from database settings.
     */
    private function loadDatabaseConfigs(): void
    {
        try {
            // Update CORS allowed origins from database
            $corsOrigins = ApiSetting::get('cors_allowed_origins', env('CORS_ALLOWED_ORIGINS', ''));

            if (! empty($corsOrigins)) {
                $corsArray = array_filter(array_map('trim', explode(',', $corsOrigins)));
                if (! empty($corsArray)) {
                    config(['cors.allowed_origins' => $corsArray]);
                }
            }

            // Update Sanctum token prefix from database
            $tokenPrefix = ApiSetting::get('sanctum_token_prefix', env('SANCTUM_TOKEN_PREFIX', ''));
            config(['sanctum.token_prefix' => $tokenPrefix ?? '']);
        } catch (\Throwable $e) {
            // Silently fail if database is not available (e.g., during migrations)
            // Log the error for debugging purposes
            if (app()->environment('local')) {
                logger()->debug('Failed to load database configs: '.$e->getMessage());
            }
        }
    }
}
