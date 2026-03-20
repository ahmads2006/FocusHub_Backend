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
        // Since we want to ensure no auto-increment and proper UUIDs
        // In a fresh-ish project, we can drop and recreate if needed, 
        // but here we just modify to ensure constraints are correct.
        if (config('database.default') !== 'sqlite') {
            Schema::table('album_user', function (Blueprint $table) {
                // The previous migration already used foreignUuid,
                // but we'll make sure there's no auto-increment ID if it exists.
                if (Schema::hasColumn('album_user', 'id')) {
                    $table->dropColumn('id');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('album_user', function (Blueprint $table) {
            $table->id()->first();
        });
    }
};
