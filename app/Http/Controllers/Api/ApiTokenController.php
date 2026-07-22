<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ApiTokenController extends Controller
{
    /**
     * Display a listing of the user's API tokens.
     */
    public function index(Request $request): JsonResponse
    {
        $apiToken = ApiToken::fromRequest($request);
        $userId = $apiToken->user_id;

        $tokens = ApiToken::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get(['id', 'name', 'abilities', 'allowed_ips', 'last_used_at', 'expires_at', 'created_at']);

        return response()->json(['data' => $tokens]);
    }

    /**
     * Store a newly created API token.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'abilities' => 'nullable|array',
            'abilities.*' => 'string',
            'allowed_ips' => 'nullable|string',
            'expires_at' => 'nullable|date|after:now',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $apiToken = ApiToken::fromRequest($request);
        $userId = $apiToken->user_id;

        // Generate a secure random token
        $plainTextToken = Str::random(40);
        $tokenHash = hash('sha256', $plainTextToken);

        // Set default abilities if none provided
        $abilities = $request->abilities ?? ['*'];

        // Create the token record
        $token = ApiToken::create([
            'user_id' => $userId,
            'name' => $request->name,
            'token' => $tokenHash,
            'abilities' => $abilities,
            'allowed_ips' => $request->allowed_ips,
            'expires_at' => $request->expires_at,
        ]);

        // Return the new token with the plain text token (will only be shown once)
        return response()->json([
            'message' => 'API token created successfully',
            'data' => [
                'id' => $token->id,
                'name' => $token->name,
                'token' => $plainTextToken, // This is the only time the token will be visible
                'abilities' => $token->abilities,
                'allowed_ips' => $token->allowed_ips,
                'expires_at' => $token->expires_at,
                'created_at' => $token->created_at,
            ],
        ], 201);
    }

    /**
     * Update the specified API token.
     *
     * @param  int  $id
     */
    public function update(Request $request, $id): JsonResponse
    {
        $apiToken = ApiToken::fromRequest($request);
        $userId = $apiToken->user_id;

        $token = ApiToken::where('user_id', $userId)
            ->where('id', $id)
            ->firstOrFail();

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'abilities' => 'nullable|array',
            'abilities.*' => 'string',
            'allowed_ips' => 'nullable|string',
            'expires_at' => 'nullable|date|after:now',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Update the token
        $token->update($request->only([
            'name', 'abilities', 'allowed_ips', 'expires_at',
        ]));

        return response()->json([
            'message' => 'API token updated successfully',
            'data' => $token->only(['id', 'name', 'abilities', 'allowed_ips', 'expires_at', 'created_at']),
        ]);
    }

    /**
     * Remove the specified API token.
     *
     * @param  int  $id
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $apiToken = ApiToken::fromRequest($request);
        $userId = $apiToken->user_id;

        $token = ApiToken::where('user_id', $userId)
            ->where('id', $id)
            ->firstOrFail();

        $token->delete();

        return response()->json([
            'message' => 'API token deleted successfully',
        ]);
    }
}
