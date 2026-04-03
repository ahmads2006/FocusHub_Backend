<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_settings', function (Blueprint $table) {
            // Replace dual booleans (use_text_watermark, use_logo_watermark) with single mode
            $table->string('watermark_mode', 10)->default('text')->after('watermark_opacity');
            // 'text' = show text watermark, 'logo' = show logo watermark
        });

        // Migrate existing data: if user had use_logo_watermark = 1, set mode to 'logo'
        \Illuminate\Support\Facades\DB::table('user_settings')
            ->where('use_logo_watermark', true)
            ->update(['watermark_mode' => 'logo']);
    }

    public function down(): void
    {
        Schema::table('user_settings', function (Blueprint $table) {
            $table->dropColumn('watermark_mode');
        });
    }
};
