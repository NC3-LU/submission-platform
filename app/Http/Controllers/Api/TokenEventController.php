<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\TokenEventRequest;
use App\Http\Resources\ApiTokenEventResource;
use App\Models\ApiToken;
use App\Models\ApiTokenEvent;
use Carbon\CarbonImmutable;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class TokenEventController extends Controller
{
    public function index(TokenEventRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();
        $query = ApiTokenEvent::where('target_user_id', ApiToken::fromRequest($request)->user_id);
        if (isset($filters['action'])) {
            $query->where('action', $filters['action']);
        }
        if (isset($filters['token_id'])) {
            $query->where('target_token_id', $filters['token_id']);
        }
        if (isset($filters['from'])) {
            $query->where('created_at', '>=', CarbonImmutable::parse($filters['from']));
        }
        if (isset($filters['to'])) {
            $end = CarbonImmutable::parse($filters['to']);
            $query->where('created_at', '<=', strlen($filters['to']) === 10 ? $end->endOfDay() : $end);
        }

        return ApiTokenEventResource::collection($query->orderByDesc('created_at')->orderByDesc('id')
            ->paginate($filters['per_page'] ?? 25)->withQueryString());
    }
}
