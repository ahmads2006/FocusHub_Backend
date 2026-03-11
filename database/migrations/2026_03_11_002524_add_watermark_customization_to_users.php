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
        Schema::table('users', function (Blueprint $table) {
            $table->string('watermark_text_color')->default('#ffffff')->after('dynamic_watermark');
            $table->string('watermark_neon_color')->default('#800080')->after('watermark_text_color');
            $table->float('watermark_opacity')->default(0.8)->after('watermark_neon_color');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['watermark_text_color', 'watermark_neon_color', 'watermark_opacity']);
        });
    }
};
