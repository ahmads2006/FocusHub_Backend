<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('image_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('image_id')->constrained('images')->cascadeOnDelete();
            $table->boolean('allow_download')->default(true);
            $table->boolean('watermark_on_download')->default(false);
            $table->boolean('copyright_enabled')->default(false);
            $table->boolean('is_comparison')->default(false);
            $table->unsignedInteger('views_count')->default(0);
            $table->unsignedInteger('downloads_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('image_settings');
    }
};
