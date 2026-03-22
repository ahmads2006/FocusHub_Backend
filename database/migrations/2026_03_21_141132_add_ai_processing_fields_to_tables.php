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
        Schema::table('media_ai_metadata', function (Blueprint $table) {
            $table->string('quality_grade')->nullable()->after('is_sensitive');
            $table->string('category')->nullable()->after('quality_grade');
        });

        Schema::table('image_storage', function (Blueprint $table) {
            $table->text('enhanced_url')->nullable()->after('imagekit_file_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('media_ai_metadata', function (Blueprint $table) {
            $table->dropColumn(['quality_grade', 'category']);
        });

        Schema::table('image_storage', function (Blueprint $table) {
            $table->dropColumn('enhanced_url');
        });
    }
};
