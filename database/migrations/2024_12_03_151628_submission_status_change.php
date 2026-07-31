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
        // SQLite cannot drop and recreate a column in the same table alteration.
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Schema::table('submissions', function (Blueprint $table) {
            $table->enum('status', [
                'draft',
                'ongoing',
                'submitted',
                'under_review',
                'completed',
            ])->default('draft');
            $table->json('status_metadata')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropColumn(['status', 'status_metadata']);
            $table->foreignId('status_type_id')->constrained('status_types');
        });
    }
};
