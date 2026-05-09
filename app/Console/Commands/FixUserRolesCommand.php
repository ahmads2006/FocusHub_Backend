<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class FixUserRolesCommand extends Command
{
    protected $signature = 'user:fix-roles {email} {role}';
    protected $description = 'Fixes user role mismatch and promotes user correctly';

    public function handle()
    {
        $email = $this->argument('email');
        $roleName = $this->argument('role'); // e.g. super-admin

        $user = User::where('email', $email)->first();
        if (!$user) {
            $this->error("User not found");
            return 1;
        }

        // 1. Ensure Spatie role exists
        Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        $user->syncRoles([$roleName]);

        // 2. Fix the ENUM column in the database to allow the hyphenated version
        $this->info("Updating database ENUM to allow '$roleName'...");
        try {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('super_admin', 'super-admin', 'admin', 'user', 'photographer') DEFAULT 'user'");
        } catch (\Exception $e) {
            $this->warn("ENUM update failed: " . $e->getMessage());
        }

        // 3. Force update the role column
        DB::table('users')->where('id', $user->id)->update(['role' => $roleName]);

        $this->info("Successfully promoted $email to $roleName and fixed DB ENUM.");
        return 0;
    }
}
