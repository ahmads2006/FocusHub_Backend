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
        Schema::table('images', function (Blueprint $table) {
            if (!Schema::hasColumn('images', 'md5_hash')) {
                $table->string('md5_hash')->nullable()->index();
            }
            if (!Schema::hasColumn('images', 'is_visible')) {
                $table->boolean('is_visible')->default(true);
            }
            if (!Schema::hasColumn('images', 'is_sensitive')) {
                $table->boolean('is_sensitive')->default(false);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('images', function (Blueprint $table) {
            if (Schema::hasColumn('images', 'md5_hash')) {
                $table->dropColumn('md5_hash');
            }
            if (Schema::hasColumn('images', 'is_visible')) {
                $table->dropColumn('is_visible');
            }
        });
    }
};
