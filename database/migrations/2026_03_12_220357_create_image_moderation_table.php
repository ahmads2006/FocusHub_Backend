<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('image_moderation', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('image_id')->constrained('images')->cascadeOnDelete();
            $table->enum('status', ['pending', 'approved', 'pending_review', 'rejected', 'under_review'])
                  ->default('approved');
            $table->boolean('is_visible')->default(true);
            $table->boolean('is_sensitive')->default(false);
            $table->string('sensitivity_reason')->nullable();
            $table->json('ai_metadata')->nullable()->comment('Sightengine + Python auditor results');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('image_moderation');
    }
};
