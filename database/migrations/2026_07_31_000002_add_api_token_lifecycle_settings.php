<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $settings = [
            [
                'key' => 'api_token_prefix',
                'label' => 'API Token Prefix',
                'value' => 'nc3_',
                'type' => 'text',
                'description' => 'Prefix applied to custom API tokens for identification and secret scanning.',
            ],
            [
                'key' => 'api_token_default_lifetime_days',
                'label' => 'Default API Token Lifetime',
                'value' => null,
                'type' => 'number',
                'description' => 'Optional default lifetime in days. Blank allows tokens without an expiration.',
            ],
            [
                'key' => 'api_token_max_lifetime_days',
                'label' => 'Maximum API Token Lifetime',
                'value' => null,
                'type' => 'number',
                'description' => 'Optional maximum lifetime in days. Blank permits any requested expiration.',
            ],
            [
                'key' => 'api_token_max_active_per_user',
                'label' => 'Maximum Active API Tokens',
                'value' => null,
                'type' => 'number',
                'description' => 'Optional active-token quota per user. Blank disables the quota.',
            ],
        ];

        foreach ($settings as $setting) {
            DB::table('api_settings')->updateOrInsert(
                ['key' => $setting['key']],
                [...$setting, 'created_at' => $now, 'updated_at' => $now]
            );
        }
    }

    public function down(): void
    {
        DB::table('api_settings')->whereIn('key', [
            'api_token_prefix',
            'api_token_default_lifetime_days',
            'api_token_max_lifetime_days',
            'api_token_max_active_per_user',
        ])->delete();
    }
};
