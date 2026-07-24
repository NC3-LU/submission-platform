<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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
        'submissions:read',
        'submissions:create',
        'submissions:update',
        'submissions:delete',
        'tokens:manage',
    ];

    /**
     * Granted when a token is created through the API without an explicit
     * ability list. Least privilege: the caller must ask for more.
     *
     * @var array<int, string>
     */
    public const DEFAULT_ABILITIES = ['forms:read'];

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
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if the token can perform a given ability.
     *
     * @param  string  $ability
     * @return bool
     */
    public function can($ability)
    {
        return in_array('*', $this->abilities) ||
               in_array($ability, $this->abilities);
    }

    /**
     * Check if the token is expired.
     *
     * @return bool
     */
    public function isExpired()
    {
        return $this->expires_at !== null && now()->gte($this->expires_at);
    }

    /**
     * Check if request IP is allowed for this token.
     *
     * @param  string  $ip
     * @return bool
     */
    public function isValidIp($ip)
    {
        // If no allowed IPs are set, allow all
        if (empty($this->allowed_ips)) {
            return true;
        }

        $allowedIps = explode(',', $this->allowed_ips);

        return in_array($ip, array_map('trim', $allowedIps));
    }

    /**
     * Update the last used timestamp.
     *
     * @return bool
     */
    public function markAsUsed()
    {
        $this->last_used_at = now();
        $this->increment('usage_count');

        return $this->save();
    }

    /**
     * Get the API token from the request attribute.
     *
     * @param  Request  $request
     * @return ApiToken|null
     */
    public static function fromRequest($request)
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

    /**
     * Rotate the token with a new value.
     *
     * @return string The plaintext token value for one-time display
     */
    public function rotate(): string
    {
        // Generate new token
        $plainTextToken = Str::random(40);

        // Hash the token for storage
        $this->token = hash('sha256', $plainTextToken);

        // Reset usage statistics
        $this->usage_count = 0;
        $this->last_used_at = null;

        // Save the changes
        $this->save();

        // Return the plaintext token for one-time display to the user
        return $plainTextToken;
    }
}
