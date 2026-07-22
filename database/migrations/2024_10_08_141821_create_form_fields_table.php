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
        Schema::create('form_fields', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('form_id'); // Associated form
            $table->string('label')->nullable();
            $table->string('type'); // Field type: text, textarea, select, etc.
            $table->text('options')->nullable(); // For fields like select, checkbox
            $table->boolean('required')->default(false);
            $table->text('content')->nullable(); // Headers and description
            $table->integer('char_limit')->nullable();
            // $table->integer('score')->nullable(); //positive and negative
            // $table->integer('score_ignore')->nullable(); //ignore the score if question not answered
            // $table->boolean('score_show')->nullable(); //ignore the score if question not answered
            $table->integer('order')->default(0);
            $table->timestamps();

            // Foreign key constraint
            $table->foreign('form_id')->references('id')->on('forms')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_fields');
    }
};
