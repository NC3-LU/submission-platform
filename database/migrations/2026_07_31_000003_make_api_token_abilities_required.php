<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('api_tokens')
            ->whereNull('abilities')
            ->update(['abilities' => json_encode([])]);

        Schema::table('api_tokens', function (Blueprint $table): void {
            $table->json('abilities')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('api_tokens', function (Blueprint $table): void {
            $table->json('abilities')->nullable()->change();
        });
    }
};
