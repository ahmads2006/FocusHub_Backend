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
        Schema::table('image_storage', function (Blueprint $table) {
            $table->text('path')->nullable()->change();
            $table->text('original_path')->nullable()->change();
            $table->text('imagekit_file_path')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('image_storage', function (Blueprint $table) {
            $table->string('path', 255)->nullable()->change();
            $table->string('original_path', 255)->nullable()->change();
            $table->string('imagekit_file_path', 255)->nullable()->change();
        });
    }
};
