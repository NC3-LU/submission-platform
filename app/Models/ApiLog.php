<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'token_id',
        'method',
        'endpoint',
        'ip_address',
        'request_data',
        'response_code',
        'execution_time',
    ];

    protected $casts = [
        'request_data' => 'array',
        'execution_time' => 'float',
    ];

    /**
     * Get the user that performed the API request.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the token used for the API request.
     */
    public function token()
    {
        return $this->belongsTo(ApiToken::class);
    }

    /**
     * Get the request data as formatted JSON string for display.
     */
    public function getRequestDataAttribute($value)
    {
        // If the value is already a string, return it
        if (is_string($value)) {
            return $value;
        }

        // If it's an array (or has been cast to an array), convert to JSON with pretty print
        $data = is_array($value) ? $value : json_decode($value, true);

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
