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
        Schema::table('image_settings', function (Blueprint $table) {
            $table->string('watermark_type')->default('text')->after('watermark_color')->nullable();
            $table->string('watermark_text')->nullable()->after('watermark_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('image_settings', function (Blueprint $table) {
            $table->dropColumn(['watermark_type', 'watermark_text']);
        });
    }
};
