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
        Schema::create('media_ai_metadata', function (Blueprint $table) {
            $table->id();
            $table->uuid('media_id');
            $table->string('media_type'); // e.g., App\Models\Image
            $table->string('driver_name'); // e.g., imagekit, sightengine
            $table->json('raw_results')->nullable();
            $table->json('extracted_tags')->nullable();
            $table->text('ocr_text')->nullable();
            $table->boolean('is_sensitive')->default(false);
            $table->float('confidence_score')->default(0.0);
            $table->timestamps();

            $table->index(['media_id', 'media_type'], 'media_ai_polymorphic_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_ai_metadata');
    }
};
