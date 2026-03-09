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
        Schema::create('banned_image_hashes', function (Blueprint $table) {
            $table->id();
            $table->string('hash')->unique();
            $table->string('reason')->nullable(); // e.g., 'API Reject', 'Manual'
            $table->json('details')->nullable(); // Store rejection details
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('banned_image_hashes');
    }
};
