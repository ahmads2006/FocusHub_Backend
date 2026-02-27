<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('albums', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->boolean('is_private')->default(false);
            $table->boolean('is_collaborative')->default(false);
            $table->string('cover_image')->nullable(); // Fallback/Reference path
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('albums');
    }
};
