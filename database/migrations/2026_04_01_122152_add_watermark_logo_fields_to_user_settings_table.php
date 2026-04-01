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
        Schema::table('user_settings', function (Blueprint $table) {
            $table->string('watermark_logo')->nullable()->after('watermark_opacity');
            $table->boolean('use_text_watermark')->default(true)->after('watermark_logo');
            $table->boolean('use_logo_watermark')->default(false)->after('use_text_watermark');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_settings', function (Blueprint $table) {
            $table->dropColumn(['watermark_logo', 'use_text_watermark', 'use_logo_watermark']);
        });
    }
};
