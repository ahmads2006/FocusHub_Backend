<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_ai_metadata', function (Blueprint $table) {
            $table->text('caption')->nullable()->after('ocr_text');
        });
    }

    public function down(): void
    {
        Schema::table('media_ai_metadata', function (Blueprint $table) {
            $table->dropColumn('caption');
        });
    }
};
