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
        Schema::create('scan_results', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('submission_id');
            $table->unsignedBigInteger('submission_value_id'); // Changed from uuid to unsignedBigInteger to match submission_values table
            $table->boolean('is_malicious')->default(false);
            $table->json('scan_results')->nullable();
            $table->string('scanner_used')->default('pandora');
            $table->string('filename');
            $table->timestamps();

            $table->foreign('submission_id')->references('id')->on('submissions')->onDelete('cascade');
            $table->foreign('submission_value_id')->references('id')->on('submission_values')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scan_results');
    }
};
