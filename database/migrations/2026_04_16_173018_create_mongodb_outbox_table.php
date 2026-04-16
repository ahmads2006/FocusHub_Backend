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
        Schema::create('mongodb_outbox', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('collection');
            $table->string('operation')->default('insert');
            $table->json('payload');
            $table->string('status')->default('pending');
            $table->integer('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mongodb_outbox');
    }
};
