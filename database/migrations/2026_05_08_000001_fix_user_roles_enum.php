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
        // Update the enum to include super-admin (with hyphen)
        // Note: In MySQL, we need to use a raw statement for ENUM changes usually, 
        // or just change the column type to string temporarily if we want to be safe.
        // But since we are in a production-like environment, let's use the most robust way.
        
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check");
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('super_admin', 'super-admin', 'admin', 'user', 'photographer'))");
        } else {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('super_admin', 'super-admin', 'admin', 'user', 'photographer') DEFAULT 'user'");
        }
        
        // Also ensure any existing super_admin becomes super-admin for consistency
        DB::table('users')->where('role', 'super_admin')->update(['role' => 'super-admin']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check");
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('super_admin', 'user', 'photographer'))");
        } else {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('super_admin', 'user', 'photographer') DEFAULT 'user'");
        }
    }
};
