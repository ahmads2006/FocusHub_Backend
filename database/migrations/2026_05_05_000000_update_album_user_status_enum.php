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
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE album_user DROP CONSTRAINT IF EXISTS album_user_status_check");
            DB::statement("ALTER TABLE album_user ADD CONSTRAINT album_user_status_check CHECK (status IN ('invited', 'accepted', 'pending'))");
        } else {
            DB::statement("ALTER TABLE album_user MODIFY COLUMN status ENUM('invited', 'accepted', 'pending') DEFAULT 'invited'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE album_user DROP CONSTRAINT IF EXISTS album_user_status_check");
            DB::statement("ALTER TABLE album_user ADD CONSTRAINT album_user_status_check CHECK (status IN ('invited', 'accepted'))");
        } else {
            DB::statement("ALTER TABLE album_user MODIFY COLUMN status ENUM('invited', 'accepted') DEFAULT 'invited'");
        }
    }
};
