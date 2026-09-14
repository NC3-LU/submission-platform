<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreApiTokenRequest;
use App\Http\Requests\Api\UpdateApiTokenRequest;
use App\Http\Resources\ApiTokenResource;
use App\Models\ApiToken;
use App\Services\ApiTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ApiTokenController extends Controller
{
    public function __construct(private readonly ApiTokenService $tokens) {}

    /**
     * Display a listing of the user's API tokens.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $apiToken = ApiToken::fromRequest($request);
        $userId = $apiToken->user_id;

        $tokens = ApiToken::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get(['id', 'name', 'token', 'abilities', 'allowed_ips', 'usage_count', 'last_used_at', 'expires_at', 'created_at']);

        return ApiTokenResource::collection($tokens);
    }

    /**
     * Store a newly created API token.
     */
    public function store(StoreApiTokenRequest $request): JsonResponse
    {
        $apiToken = ApiToken::fromRequest($request);
        $userId = $apiToken->user_id;
        $data = $request->validated();

        // Least privilege: an omitted ability list used to mean '*', which let
        // any token mint an unrestricted one.
        $abilities = $data['abilities'] ?? ApiToken::DEFAULT_ABILITIES;

        if ($denied = $this->abilitiesBeyond($apiToken, $abilities)) {
            return $this->escalationRefused($denied);
        }

        $issuedToken = $this->tokens->issue($userId, [
            'name' => $data['name'],
            'abilities' => $abilities,
            'allowed_ips' => $data['allowed_ips'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
        ], actorToken: $apiToken, ipAddress: $request->ip());
        $token = $issuedToken->token;

        // Return the new token with the plain text token (will only be shown once)
        return response()->json([
            'message' => 'API token created successfully',
            'data' => [
                ...(new ApiTokenResource($token))->resolve($request),
                'token' => $issuedToken->plainTextToken,
            ],
        ], 201)->header('Cache-Control', 'no-store, private');
    }

    /**
     * Update the specified API token.
     *
     * @param  int  $id
     */
    public function update(UpdateApiTokenRequest $request, $id): JsonResponse
    {
        $apiToken = ApiToken::fromRequest($request);
        $userId = $apiToken->user_id;

        $token = ApiToken::where('user_id', $userId)
            ->where('id', $id)
            ->firstOrFail();

        $data = $request->validated();

        if (array_key_exists('abilities', $data)
            && ($denied = $this->abilitiesBeyond($apiToken, $data['abilities']))) {
            return $this->escalationRefused($denied);
        }

        $token = $this->tokens->update(
            $token,
            $data,
            actorToken: $apiToken,
            ipAddress: $request->ip(),
        );

        return response()->json([
            'message' => 'API token updated successfully',
            'data' => (new ApiTokenResource($token))->resolve($request),
        ]);
    }

    /**
     * Remove the specified API token.
     *
     * @param  int  $id
     */
    public function destroy(Request $request, $id): Response
    {
        $apiToken = ApiToken::fromRequest($request);
        $userId = $apiToken->user_id;

        $token = ApiToken::where('user_id', $userId)
            ->where('id', $id)
            ->firstOrFail();

        $this->tokens->revoke($token, actorToken: $apiToken, ipAddress: $request->ip());

        return response()->noContent();
    }

    public function rotate(Request $request, string $token): JsonResponse
    {
        $apiToken = ApiToken::fromRequest($request);
        $target = ApiToken::query()
            ->where('user_id', $apiToken->user_id)
            ->findOrFail($token);

        $rotated = $this->tokens->rotate($target, actorToken: $apiToken, ipAddress: $request->ip());

        return response()->json([
            'message' => 'API token rotated successfully',
            'data' => [
                ...(new ApiTokenResource($rotated->token))->resolve($request),
                'token' => $rotated->plainTextToken,
            ],
        ])->header('Cache-Control', 'no-store, private');
    }

    /**
     * Abilities in $requested that the calling token does not itself hold.
     *
     * A token may only ever hand out a subset of its own reach — otherwise the
     * token endpoints are a privilege-escalation primitive.
     *
     * @param  array<int, string>  $requested
     * @return array<int, string>
     */
    private function abilitiesBeyond(ApiToken $caller, array $requested): array
    {
        return array_values(array_filter(
            $requested,
            fn ($ability) => ! $caller->can($ability)
        ));
    }

    /**
     * @param  array<int, string>  $denied
     */
    private function escalationRefused(array $denied): JsonResponse
    {
        return response()->json([
            'message' => 'Validation failed',
            'errors' => [
                'abilities' => [
                    'A token cannot grant abilities it does not hold: '.implode(', ', $denied).'.',
                ],
            ],
        ], 422);
    }
}
