<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_delivery_locks', function (Blueprint $table) {
            // A separate quota mutex avoids locking users after an enclosing form transaction.
            $table->unsignedBigInteger('user_id')->primary();
            $table->timestamp('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_delivery_locks');
    }
};
