<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('image_meta', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('image_id')->constrained('images')->cascadeOnDelete();
            $table->json('exif_data')->nullable()->comment('Raw EXIF data');
            $table->json('technical_specs')->nullable()->comment('Camera/lens specs parsed from EXIF');
            $table->json('specs')->nullable()->comment('Additional specs overlay');
            $table->mediumText('metadata')->nullable()->comment('Encrypted sensitive metadata (thumbnails etc)');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('image_meta');
    }
};
