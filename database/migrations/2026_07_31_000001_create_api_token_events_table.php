<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_token_events', function (Blueprint $table): void {
            $table->id();
            $table->string('action', 32)->index();
            $table->unsignedBigInteger('actor_user_id')->nullable()->index();
            $table->unsignedBigInteger('actor_token_id')->nullable()->index();
            $table->unsignedBigInteger('target_user_id')->index();
            $table->unsignedBigInteger('target_token_id')->index();
            $table->string('target_token_name');
            $table->string('target_token_fingerprint', 16);
            $table->string('ip_address', 45)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_token_events');
    }
};
