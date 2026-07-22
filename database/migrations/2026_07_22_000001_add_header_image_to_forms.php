<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->string('header_image')->nullable()->after('description');
            $table->unsignedTinyInteger('header_image_position')->default(50)->after('header_image');
            $table->string('header_theme_color', 7)->nullable()->after('header_image_position');
        });
    }

    public function down(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->dropColumn(['header_image', 'header_image_position', 'header_theme_color']);
        });
    }
};
