<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('api_tokens', function (Blueprint $table) {
            // Add usage count column with default value of 0
            $table->unsignedBigInteger('usage_count')->default(0)->after('allowed_ips')
                ->comment('Number of times token has been used');

            // Add index for faster queries when sorting/filtering by usage
            $table->index('usage_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('api_tokens', function (Blueprint $table) {
            // Remove index first to avoid errors
            $table->dropIndex(['usage_count']);

            // Then remove the column
            $table->dropColumn('usage_count');
        });
    }
};
