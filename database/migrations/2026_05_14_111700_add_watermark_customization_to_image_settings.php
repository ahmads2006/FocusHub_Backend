<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('image_settings', function (Blueprint $table) {
            $table->unsignedInteger('watermark_font_size')->default(80)->after('watermark_on_download');
            $table->unsignedInteger('watermark_opacity')->default(70)->after('watermark_font_size');
            $table->string('watermark_color', 8)->default('FFFFFF')->after('watermark_opacity');
        });
    }

    public function down(): void
    {
        Schema::table('image_settings', function (Blueprint $table) {
            $table->dropColumn(['watermark_font_size', 'watermark_opacity', 'watermark_color']);
        });
    }
};
