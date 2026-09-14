<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class ApiTokenEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'action',
        'actor_user_id',
        'actor_token_id',
        'target_user_id',
        'target_token_id',
        'target_token_name',
        'target_token_fingerprint',
        'ip_address',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'immutable_datetime',
    ];
}
