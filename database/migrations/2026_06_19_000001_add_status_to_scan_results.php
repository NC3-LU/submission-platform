<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('scan_results', function (Blueprint $table) {
            // pending = queued/not yet scanned, clean = safe, malicious = flagged,
            // failed = scan could not complete (treated as untrusted when blocking).
            $table->string('status')->default('pending')->after('is_malicious')->index();
        });

        // Backfill existing rows from the boolean flag.
        DB::table('scan_results')->where('is_malicious', true)->update(['status' => 'malicious']);
        DB::table('scan_results')->where('is_malicious', false)->update(['status' => 'clean']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('scan_results', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
