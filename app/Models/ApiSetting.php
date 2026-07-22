<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class ApiSetting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'key',
        'label',
        'value',
        'type',
        'attributes',
        'description',
    ];

    protected $casts = [
        'attributes' => 'array',
    ];

    /**
     * Get a setting value by key.
     *
     * @param  mixed  $default
     * @return mixed
     */
    public static function get(string $key, $default = null)
    {
        return Cache::remember("api_setting:{$key}", 3600, function () use ($key, $default) {
            $setting = static::find($key);

            return $setting ? $setting->value : $default;
        });
    }

    /**
     * Set a setting value by key.
     *
     * @param  mixed  $value
     */
    public static function set(string $key, $value): bool
    {
        $setting = static::findOrFail($key);
        $setting->value = $value;
        $result = $setting->save();

        // Clear cache
        Cache::forget("api_setting:{$key}");

        return $result;
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Clear cache when model is updated
        static::saved(function ($setting) {
            Cache::forget("api_setting:{$setting->key}");
        });

        static::deleted(function ($setting) {
            Cache::forget("api_setting:{$setting->key}");
        });
    }
}
