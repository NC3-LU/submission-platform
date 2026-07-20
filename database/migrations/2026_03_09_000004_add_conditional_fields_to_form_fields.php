<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_fields', function (Blueprint $table) {
            $table->unsignedBigInteger('depends_on_field_id')->nullable()->after('char_limit');
            $table->string('depends_on_value')->nullable()->after('depends_on_field_id');

            $table->foreign('depends_on_field_id')
                ->references('id')
                ->on('form_fields')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('form_fields', function (Blueprint $table) {
            $table->dropForeign(['depends_on_field_id']);
            $table->dropColumn(['depends_on_field_id', 'depends_on_value']);
        });
    }
};
