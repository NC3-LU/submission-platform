<?php

namespace Tests\Unit;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RateLimiterDefaultsTest extends TestCase
{
    public function test_api_rate_limiter_returns_limit_without_api_settings(): void
    {
        Cache::flush();

        $request = Request::create('/api/test', 'GET');
        $limiter = RateLimiter::limiter('api');

        $result = $limiter($request);

        $this->assertInstanceOf(Limit::class, $result);
    }

    public function test_api_token_is_resolved_before_authenticated_rate_limiting(): void
    {
        $middleware = Route::getRoutes()->getByName('api.forms.index')->gatherMiddleware();

        $tokenPosition = array_search('api.token.ip', $middleware, true);
        $limiterPosition = array_search('throttle:api', $middleware, true);

        $this->assertIsInt($tokenPosition);
        $this->assertIsInt($limiterPosition);
        $this->assertLessThan($limiterPosition, $tokenPosition);
    }
}
