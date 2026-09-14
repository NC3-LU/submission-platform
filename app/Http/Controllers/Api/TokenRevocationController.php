<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\TokenRevocationRequest;
use App\Models\ApiToken;
use App\Services\ApiTokenService;
use Illuminate\Http\JsonResponse;

final class TokenRevocationController extends Controller
{
    public function store(TokenRevocationRequest $request, ApiTokenService $tokens): JsonResponse
    {
        return response()->json(['data' => $tokens->revokeBatch(ApiToken::fromRequest($request), $request->validated(), $request->ip())]);
    }
}
