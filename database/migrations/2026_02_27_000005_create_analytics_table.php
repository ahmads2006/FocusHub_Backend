<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('photo_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['view', 'download']);
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics');
    }
};
