<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class DeprecatedApiRoute
{
    public function handle(Request $request, Closure $next, string $successor): Response
    {
        $response = $next($request);
        $parameters = $request->route()?->parameters() ?? [];
        $response->headers->set('Deprecation', '@1789344000');
        $response->headers->set('Sunset', 'Thu, 01 Apr 2027 00:00:00 GMT');
        $response->headers->set('Link', '<'.route($successor, $parameters).'>; rel="successor-version"');

        return $response;
    }
}
