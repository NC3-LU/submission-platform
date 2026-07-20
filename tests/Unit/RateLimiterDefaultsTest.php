<?php

namespace Tests\Unit;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
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
}
