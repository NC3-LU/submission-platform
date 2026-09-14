<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
        });
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            // Change in place: dropping/recreating status would reset existing responses.
            $table->string('status', 32)->default('draft')->change();
            $table->uuid('request_key')->nullable()->unique();
            $table->index(['form_id', 'status', 'updated_at'], 'submissions_form_status_activity');
            $table->index(['user_id', 'form_id', 'status'], 'submissions_user_form_status');
        });
    }

    public function down(): void
    {
        // Keep the protective constraints and expanded status domain on rollback.
        // Both are compatible with the previous application; restoring cascade deletion is unsafe.
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropIndex('submissions_form_status_activity');
            $table->dropIndex('submissions_user_form_status');
            $table->dropUnique(['request_key']);
            $table->dropColumn('request_key');
        });
    }
};
