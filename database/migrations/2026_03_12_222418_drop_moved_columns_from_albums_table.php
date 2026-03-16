<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('albums', function (Blueprint $table) {
            $table->dropColumn(['privacy', 'is_collaborative', 'cover_image', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('albums', function (Blueprint $table) {
            $table->enum('privacy', ['public', 'private', 'hidden'])->default('public');
            $table->boolean('is_collaborative')->default(false);
            $table->string('cover_image')->nullable();
            $table->string('status')->nullable();
        });
    }
};
