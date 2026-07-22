<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormAccessLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'token',
        'expires_at',
        'form_id',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function isValid(): bool
    {
        // Check if the link has an expiration date and if it's in the future
        if ($this->expires_at === null) {
            return true;
        }

        return $this->expires_at->isFuture();
    }

    public static function findValidByToken(string $token): ?self
    {
        return static::where('token', $token)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->first();
    }
}
