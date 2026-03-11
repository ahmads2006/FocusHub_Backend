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
        Schema::table('images', function (Blueprint $table) {
            $table->json('specs')->nullable()->after('technical_specs');
            $table->string('imagekit_file_id')->nullable()->after('specs');
            $table->string('imagekit_file_path')->nullable()->after('imagekit_file_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('images', function (Blueprint $table) {
            $table->dropColumn(['specs', 'imagekit_file_id', 'imagekit_file_path']);
        });
    }
};
