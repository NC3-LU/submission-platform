<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_endpoints', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('form_id')->constrained()->cascadeOnDelete();
            $table->string('url', 2048);
            $table->text('secret');
            $table->json('events');
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->index(['form_id', 'enabled']);
        });
        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('webhook_endpoint_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->uuid('event_id');
            $table->string('event_type', 48);
            $table->json('payload');
            $table->string('status', 16)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('max_attempts')->default(5);
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->string('error_code', 32)->nullable();
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamps();
            $table->unique(['webhook_endpoint_id', 'event_id']);
            $table->index(['status', 'next_attempt_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhook_endpoints');
    }
};
