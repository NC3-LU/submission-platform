<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ApiSetting;
use App\Models\ApiToken;
use App\Models\ApiTokenEvent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ApiTokenService
{
    /**
     * @param  array{name: string, abilities: array<int, string>, allowed_ips?: ?string, expires_at?: mixed}  $attributes
     */
    public function issue(
        int $userId,
        array $attributes,
        ?ApiToken $actorToken = null,
        ?User $actorUser = null,
        ?string $ipAddress = null,
    ): IssuedApiToken {
        return DB::transaction(function () use ($userId, $attributes, $actorToken, $actorUser, $ipAddress): IssuedApiToken {
            User::query()->lockForUpdate()->findOrFail($userId);
            $this->ensureActiveTokenLimitAllowsIssuance($userId);

            $plainTextToken = $this->generatePlainTextToken();
            $token = ApiToken::create([
                'user_id' => $userId,
                'name' => $attributes['name'],
                'token' => hash('sha256', $plainTextToken),
                'abilities' => $attributes['abilities'],
                'allowed_ips' => $attributes['allowed_ips'] ?? null,
                'expires_at' => $this->resolveExpiration($attributes['expires_at'] ?? null, applyDefault: true),
            ]);

            ApiTokenEvent::create([
                'action' => 'created',
                'actor_user_id' => $actorUser?->id ?? $actorToken?->user_id,
                'actor_token_id' => $actorToken?->id,
                'target_user_id' => $token->user_id,
                'target_token_id' => $token->id,
                'target_token_name' => $token->name,
                'target_token_fingerprint' => substr($token->token, 0, 16),
                'ip_address' => $ipAddress,
            ]);

            return new IssuedApiToken($token, $plainTextToken);
        });
    }

    public function revoke(
        ApiToken $target,
        ?ApiToken $actorToken = null,
        ?User $actorUser = null,
        ?string $ipAddress = null,
    ): void {
        DB::transaction(function () use ($target, $actorToken, $actorUser, $ipAddress): void {
            ApiTokenEvent::create([
                'action' => 'revoked',
                'actor_user_id' => $actorUser?->id ?? $actorToken?->user_id,
                'actor_token_id' => $actorToken?->id,
                'target_user_id' => $target->user_id,
                'target_token_id' => $target->id,
                'target_token_name' => $target->name,
                'target_token_fingerprint' => substr($target->token, 0, 16),
                'ip_address' => $ipAddress,
            ]);

            $target->delete();
        });
    }

    public function rotate(
        ApiToken $target,
        ?ApiToken $actorToken = null,
        ?User $actorUser = null,
        ?string $ipAddress = null,
    ): IssuedApiToken {
        return DB::transaction(function () use ($target, $actorToken, $actorUser, $ipAddress): IssuedApiToken {
            $lockedTarget = ApiToken::query()->lockForUpdate()->findOrFail($target->id);
            $oldFingerprint = substr($lockedTarget->token, 0, 16);
            $plainTextToken = $this->generatePlainTextToken();

            $lockedTarget->forceFill([
                'token' => hash('sha256', $plainTextToken),
                'usage_count' => 0,
                'last_used_at' => null,
            ])->save();

            ApiTokenEvent::create([
                'action' => 'rotated',
                'actor_user_id' => $actorUser?->id ?? $actorToken?->user_id,
                'actor_token_id' => $actorToken?->id,
                'target_user_id' => $lockedTarget->user_id,
                'target_token_id' => $lockedTarget->id,
                'target_token_name' => $lockedTarget->name,
                'target_token_fingerprint' => $oldFingerprint,
                'ip_address' => $ipAddress,
                'metadata' => [
                    'replacement_fingerprint' => substr($lockedTarget->token, 0, 16),
                ],
            ]);

            return new IssuedApiToken($lockedTarget, $plainTextToken);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(
        ApiToken $target,
        array $attributes,
        ?ApiToken $actorToken = null,
        ?User $actorUser = null,
        ?string $ipAddress = null,
    ): ApiToken {
        return DB::transaction(function () use ($target, $attributes, $actorToken, $actorUser, $ipAddress): ApiToken {
            $lockedTarget = ApiToken::query()->lockForUpdate()->findOrFail($target->id);

            if (array_key_exists('expires_at', $attributes)) {
                $attributes['expires_at'] = $this->resolveExpiration($attributes['expires_at'], applyDefault: false);
            }

            $lockedTarget->fill($attributes);
            $changedFields = array_keys($lockedTarget->getDirty());
            $lockedTarget->save();

            ApiTokenEvent::create([
                'action' => 'updated',
                'actor_user_id' => $actorUser?->id ?? $actorToken?->user_id,
                'actor_token_id' => $actorToken?->id,
                'target_user_id' => $lockedTarget->user_id,
                'target_token_id' => $lockedTarget->id,
                'target_token_name' => $lockedTarget->name,
                'target_token_fingerprint' => $lockedTarget->fingerprint(),
                'ip_address' => $ipAddress,
                'metadata' => ['changed_fields' => $changedFields],
            ]);

            return $lockedTarget;
        });
    }

    private function generatePlainTextToken(): string
    {
        $prefix = (string) ApiSetting::get(
            'api_token_prefix',
            ApiSetting::get('sanctum_token_prefix', 'nc3_')
        );

        return $prefix.Str::random(48);
    }

    private function resolveExpiration(mixed $expiration, bool $applyDefault): ?CarbonInterface
    {
        $resolvedExpiration = null;
        if ($expiration !== null && $expiration !== '') {
            $resolvedExpiration = CarbonImmutable::parse($expiration);
        } elseif ($applyDefault) {
            $defaultLifetimeDays = (int) ApiSetting::get('api_token_default_lifetime_days', 0);
            $resolvedExpiration = $defaultLifetimeDays > 0
                ? CarbonImmutable::now()->addDays($defaultLifetimeDays)
                : null;
        }

        $maximumLifetimeDays = (int) ApiSetting::get('api_token_max_lifetime_days', 0);
        if ($resolvedExpiration !== null
            && $maximumLifetimeDays > 0
            && $resolvedExpiration->gt(CarbonImmutable::now()->addDays($maximumLifetimeDays))) {
            throw ValidationException::withMessages([
                'expires_at' => "The expires at must not be more than {$maximumLifetimeDays} days from now.",
            ]);
        }

        return $resolvedExpiration;
    }

    private function ensureActiveTokenLimitAllowsIssuance(int $userId): void
    {
        $maximumActiveTokens = (int) ApiSetting::get('api_token_max_active_per_user', 0);
        if ($maximumActiveTokens <= 0) {
            return;
        }

        $activeTokens = ApiToken::query()
            ->where('user_id', $userId)
            ->where(fn ($query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>', now()))
            ->count();

        if ($activeTokens >= $maximumActiveTokens) {
            throw ValidationException::withMessages([
                'name' => "The active API token limit of {$maximumActiveTokens} has been reached.",
            ]);
        }
    }
}
