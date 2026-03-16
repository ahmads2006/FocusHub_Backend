<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('album_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('album_id')->constrained('albums')->cascadeOnDelete();
            $table->enum('privacy', ['public', 'private', 'hidden'])->default('public');
            $table->boolean('is_collaborative')->default(false);
            $table->string('cover_image')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('album_settings');
    }
};
