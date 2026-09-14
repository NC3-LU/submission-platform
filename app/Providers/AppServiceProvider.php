<?php

namespace App\Providers;

use App\Models\ApiSetting;
use App\Models\User;
use App\Services\OperationalHealth;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\WorkerStarting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
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
        // Looping events pause in maintenance mode; deployment still needs proof of worker startup.
        Event::listen(WorkerStarting::class, fn () => Cache::put('health:queue', now()->timestamp, 300));
        Queue::looping(fn () => Cache::put('health:queue', now()->timestamp, 300));
        Event::listen(DiagnosingHealth::class, function () {
            if (in_array(false, app(OperationalHealth::class)->checks(), true)) {
                throw new \RuntimeException('An application dependency is unavailable.');
            }
        });
        Scramble::configure()->withDocumentTransformers(function (OpenApi $openApi) {
            $openApi->secure(SecurityScheme::http('bearer'));
        });
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
        // Deliberately not derived from request()->getHost(): the Host header
        // is client-supplied, so the old `str_starts_with($host, 'test.')`
        // escape hatch let any registered user reach /docs/api by spoofing it.
        // Environments that want open docs opt in through configuration.
        if (config('app.api_docs_public', false)) {
            return true;
        }

        // Get allowed domains from database settings
        try {
            $allowedDomainsStr = ApiSetting::get(
                'api_docs_allowed_domains',
                config('app.api_docs_allowed_domains', '')
            );
        } catch (\Throwable) {
            $allowedDomainsStr = config('app.api_docs_allowed_domains', '');
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
            $corsOrigins = ApiSetting::get(
                'cors_allowed_origins',
                implode(',', config('cors.allowed_origins', []))
            );

            if (! empty($corsOrigins)) {
                $corsArray = array_filter(array_map('trim', explode(',', $corsOrigins)));
                if (! empty($corsArray)) {
                    config(['cors.allowed_origins' => $corsArray]);
                }
            }

            // Update Sanctum token prefix from database
            $tokenPrefix = ApiSetting::get('sanctum_token_prefix', config('sanctum.token_prefix', ''));
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
