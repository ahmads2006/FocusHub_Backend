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
        Schema::table('album_user', function (Blueprint $table) {
            $table->enum('status', ['invited', 'accepted'])->default('invited')->after('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('album_user', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
