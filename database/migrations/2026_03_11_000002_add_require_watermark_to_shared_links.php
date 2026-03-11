<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shared_links', function (Blueprint $table) {
            // Controls whether non-owner downloads via this link are watermarked.
            // null = follow photographer's global dynamic_watermark preference.
            // true = force watermark on download regardless of global preference.
            // false = force NO watermark on download regardless of global preference.
            $table->boolean('require_watermark')->nullable()->default(null)->after('auto_rotate');
        });
    }

    public function down(): void
    {
        Schema::table('shared_links', function (Blueprint $table) {
            $table->dropColumn('require_watermark');
        });
    }
};
