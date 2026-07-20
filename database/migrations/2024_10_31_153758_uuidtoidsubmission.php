<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $this->upSqlite();

            return;
        }

        $this->upMysql();
    }

    private function upSqlite(): void
    {
        // SQLite cannot drop primary keys — recreate tables with UUID PKs
        Schema::dropIfExists('submission_values');
        Schema::dropIfExists('submissions');

        Schema::create('submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('form_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->enum('status', ['draft', 'submitted'])->default('draft');
            $table->timestamp('last_edited_at')->nullable();
            $table->timestamps();

            $table->foreign('form_id')->references('id')->on('forms')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('submission_values', function (Blueprint $table) {
            $table->id();
            $table->uuid('submission_id');
            $table->unsignedBigInteger('form_field_id');
            $table->text('value')->nullable();
            $table->timestamps();

            $table->foreign('submission_id')->references('id')->on('submissions')->onDelete('cascade');
            $table->foreign('form_field_id')->references('id')->on('form_fields')->onDelete('cascade');
        });
    }

    private function upMysql(): void
    {
        // First, add UUID column to submissions table
        Schema::table('submissions', function (Blueprint $table) {
            $table->uuid('uuid')->after('id');
        });

        // Generate UUIDs for existing records
        DB::table('submissions')->cursor()->each(function ($submission) {
            DB::table('submissions')
                ->where('id', $submission->id)
                ->update(['uuid' => Str::uuid()]);
        });

        // Update submission_values to reference new UUID
        Schema::table('submission_values', function (Blueprint $table) {
            $table->uuid('submission_uuid')->after('submission_id');
        });

        // Copy submission UUIDs to submission_values
        DB::table('submission_values')
            ->join('submissions', 'submissions.id', '=', 'submission_values.submission_id')
            ->update(['submission_values.submission_uuid' => DB::raw('submissions.uuid')]);

        // Drop old foreign key
        Schema::table('submission_values', function (Blueprint $table) {
            $table->dropForeign(['submission_id']);
            $table->dropColumn('submission_id');
        });

        // Rename UUID columns and set up new foreign keys
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropColumn('id');
            $table->renameColumn('uuid', 'id');
            $table->primary('id');
        });

        Schema::table('submission_values', function (Blueprint $table) {
            $table->renameColumn('submission_uuid', 'submission_id');
            $table->foreign('submission_id')->references('id')->on('submissions')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        throw new \Exception('This migration cannot be reversed.');
    }
};
