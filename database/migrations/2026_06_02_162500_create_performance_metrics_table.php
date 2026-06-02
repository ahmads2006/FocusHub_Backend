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
        Schema::create('performance_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('metric_type', 32)->default('web_vitals');
            $table->string('path', 255)->index();
            $table->timestamp('ts')->nullable()->index();

            $table->decimal('lcp', 10, 2)->nullable();
            $table->decimal('inp', 10, 2)->nullable();
            $table->decimal('cls', 8, 3)->nullable();

            $table->boolean('pass_lcp')->nullable();
            $table->boolean('pass_inp')->nullable();
            $table->boolean('pass_cls')->nullable();

            $table->unsignedInteger('prefetch_attempts')->nullable();
            $table->unsignedInteger('prefetch_success')->nullable();
            $table->unsignedInteger('prefetch_failed')->nullable();
            $table->unsignedInteger('prefetch_skipped')->nullable();

            $table->text('user_agent')->nullable();
            $table->string('session_id', 128)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['metric_type', 'ts']);
            $table->index(['path', 'ts']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('performance_metrics');
    }
};
