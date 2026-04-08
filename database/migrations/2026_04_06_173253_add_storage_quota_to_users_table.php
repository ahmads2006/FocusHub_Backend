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
        Schema::table('users', function (Blueprint $table) {
            // Default: 5GB = 5 * 1024 * 1024 * 1024 bytes
            $table->unsignedBigInteger('storage_limit_bytes')->nullable()->default(5368709120)->after('email');
            $table->unsignedBigInteger('storage_used_bytes')->default(0)->after('storage_limit_bytes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['storage_limit_bytes', 'storage_used_bytes']);
        });
    }
};
