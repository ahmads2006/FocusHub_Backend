<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('image_storage', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('image_id')->constrained('images')->cascadeOnDelete();
            $table->string('path')->nullable();
            $table->string('original_path')->nullable()->comment('Clean original in secure_uploads');
            $table->string('imagekit_file_id')->nullable();
            $table->string('imagekit_file_path')->nullable();
            $table->string('md5_hash')->nullable()->index()->comment('For duplicate detection');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('image_storage');
    }
};
