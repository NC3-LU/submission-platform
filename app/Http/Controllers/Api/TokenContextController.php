<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApiTokenResource;
use App\Models\ApiSetting;
use App\Models\ApiToken;
use App\Services\ApiTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class TokenContextController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $token = ApiToken::fromRequest($request);
        $token->markAsUsed($request->ip());
        $user = $token->user;

        return response()->json(['data' => [
            'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => $user->role],
            'token' => (new ApiTokenResource($token))->resolve($request),
            'limits' => ['requests_per_minute' => max(1, (int) ApiSetting::get('rate_limit_api_authenticated', 60))],
        ]])->header('Cache-Control', 'no-store, private');
    }

    public function destroy(Request $request, ApiTokenService $tokens): Response
    {
        $token = ApiToken::fromRequest($request);
        $tokens->revoke($token, actorToken: $token, ipAddress: $request->ip());

        return response()->noContent()->header('Cache-Control', 'no-store, private');
    }
}
