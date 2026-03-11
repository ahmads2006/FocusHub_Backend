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
        Schema::create('image_metadata', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('image_id')->constrained()->onDelete('cascade');
            $table->string('camera_make')->nullable();
            $table->string('camera_model')->nullable();
            $table->string('lens_type')->nullable();
            $table->string('focal_length')->nullable();
            $table->string('aperture')->nullable();
            $table->string('shutter_speed')->nullable();
            $table->string('iso')->nullable();
            $table->dateTime('original_creation_date')->nullable();
            $table->json('extra_info')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('image_metadata');
    }
};
