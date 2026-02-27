<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('album_user', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('album_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['admin', 'contributor', 'viewer'])->default('viewer');
            $table->timestamps();

            $table->unique(['album_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('album_user');
    }
};
