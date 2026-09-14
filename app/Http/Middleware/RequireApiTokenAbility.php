<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireApiTokenAbility
{
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        $token = ApiToken::fromRequest($request);

        if (! $token?->can($ability)) {
            return new JsonResponse([
                'message' => 'API token does not have the required permissions',
            ], Response::HTTP_FORBIDDEN);
        }

        $token->markAsUsed($request->ip());

        return $next($request);
    }
}
