<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_token_events', function (Blueprint $table) {
            $table->index(['target_user_id', 'created_at', 'id'], 'token_events_user_time');
            $table->index(['target_user_id', 'action', 'created_at'], 'token_events_user_action_time');
            $table->index(['target_token_id', 'action'], 'token_events_token_action');
        });
    }

    public function down(): void
    {
        Schema::table('api_token_events', function (Blueprint $table) {
            $table->dropIndex('token_events_user_time');
            $table->dropIndex('token_events_user_action_time');
            $table->dropIndex('token_events_token_action');
        });
    }
};
