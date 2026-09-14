<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\IpUtils;

class ApiToken extends Model
{
    use HasFactory;

    /**
     * Every ability a token may hold, excluding the '*' wildcard.
     *
     * The wildcard is deliberately not listed: it can only be granted from the
     * admin panel, never through the API, so a token can never widen its own
     * reach.
     *
     * @var array<int, string>
     */
    public const ABILITIES = [
        'forms:read',
        'forms:create',
        'forms:update',
        'forms:delete',
        'forms:share',
        'submissions:read',
        'submissions:create',
        'submissions:update',
        'submissions:delete',
        'submissions:export',
        'webhooks:manage',
        'tokens:manage',
    ];

    /**
     * Granted when a token is created through the API without an explicit
     * ability list. Least privilege: the caller must ask for more.
     *
     * @var array<int, string>
     */
    public const DEFAULT_ABILITIES = ['forms:read'];

    /**
     * Abilities that may be delegated by one API token to another.
     * Token management is deliberately reserved for administrator-issued keys.
     *
     * @var array<int, string>
     */
    public const DELEGABLE_ABILITIES = [
        'forms:read',
        'forms:create',
        'forms:update',
        'forms:delete',
        'forms:share',
        'submissions:read',
        'submissions:create',
        'submissions:update',
        'submissions:delete',
        'submissions:export',
        'webhooks:manage',
    ];

    protected $fillable = [
        'user_id',
        'name',
        'token',
        'abilities',
        'allowed_ips',
        'last_used_at',
        'expires_at',
        'usage_count',
    ];

    protected $casts = [
        'abilities' => 'array',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'usage_count' => 'integer',
    ];

    /**
     * Get the user that owns the token.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if the token can perform a given ability.
     */
    public function can(string $ability): bool
    {
        $abilities = (array) $this->abilities;

        return in_array('*', $abilities, true) || in_array($ability, $abilities, true);
    }

    /**
     * Check if the token is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && now()->gte($this->expires_at);
    }

    public function fingerprint(): string
    {
        return substr($this->token, 0, 16);
    }

    /**
     * Check if request IP is allowed for this token.
     */
    public function isValidIp(string $ip): bool
    {
        // If no allowed IPs are set, allow all
        if (empty($this->allowed_ips)) {
            return true;
        }

        $allowedIps = array_filter(array_map('trim', explode(',', $this->allowed_ips)));

        return IpUtils::checkIp($ip, $allowedIps);
    }

    /**
     * Update the last used timestamp.
     */
    public function markAsUsed(?string $ipAddress = null): bool
    {
        return DB::transaction(function () use ($ipAddress): bool {
            $token = static::query()->lockForUpdate()->find($this->id);
            if (! $token) {
                return false;
            }
            // Per-request metadata already lives in api_logs. Record a new usage session after an idle hour.
            if ($token->last_used_at === null || $token->last_used_at->lte(now()->subHour())) {
                ApiTokenEvent::create([
                    'action' => 'used', 'actor_user_id' => $token->user_id, 'actor_token_id' => $token->id,
                    'target_user_id' => $token->user_id, 'target_token_id' => $token->id,
                    'target_token_name' => $token->name, 'target_token_fingerprint' => $token->fingerprint(),
                    'ip_address' => $ipAddress,
                ]);
            }
            $updated = $token->increment('usage_count', 1, ['last_used_at' => now()]) > 0;
            $this->setRawAttributes($token->getAttributes(), true);

            return $updated;
        });
    }

    /**
     * Get the API token from the request attribute.
     */
    public static function fromRequest(Request $request): ?self
    {
        return $request->attributes->get('api_token');
    }

    /**
     * Check if the token has been inactive for a specified period.
     *
     * @param  int  $days  Number of days of inactivity to consider stale
     */
    public function isStale(int $days = 90): bool
    {
        if (! $this->last_used_at) {
            // If never used, check against created_at
            return $this->created_at->addDays($days)->isPast();
        }

        return $this->last_used_at->addDays($days)->isPast();
    }
}
