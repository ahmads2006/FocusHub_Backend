<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // MySQL requires raw SQL to update ENUM values reliably
        DB::statement("ALTER TABLE album_user MODIFY COLUMN status ENUM('invited', 'accepted', 'pending') DEFAULT 'invited'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE album_user MODIFY COLUMN status ENUM('invited', 'accepted') DEFAULT 'invited'");
    }
};
