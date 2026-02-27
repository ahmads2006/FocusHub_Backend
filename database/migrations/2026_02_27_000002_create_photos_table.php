<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('photos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('album_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->enum('privacy', ['public', 'private'])->default('public');
            $table->json('exif_data')->nullable();
            $table->boolean('is_comparison')->default(false);
            $table->unsignedInteger('views_count')->default(0);
            $table->unsignedInteger('downloads_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photos');
    }
};
