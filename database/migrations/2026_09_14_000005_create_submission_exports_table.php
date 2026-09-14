<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submission_exports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Keep the cleanup record when its form/requester is deleted.
            $table->unsignedBigInteger('form_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('token_id');
            $table->string('format', 8);
            $table->string('status', 16)->default('queued');
            $table->string('path')->nullable();
            $table->unsignedInteger('row_count')->nullable();
            $table->unsignedBigInteger('bytes')->nullable();
            $table->string('error_code', 32)->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->index(['user_id', 'status', 'created_at']);
            $table->index(['form_id', 'status', 'created_at']);
            $table->index(['expires_at', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submission_exports');
    }
};
