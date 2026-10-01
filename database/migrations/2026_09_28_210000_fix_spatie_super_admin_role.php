<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Fix the Spatie role assignment for admin users.
 * 
 * The routes use middleware 'role:super-admin' (with hyphen) but the
 * Spatie role was only created as 'super_admin' (with underscore).
 * This migration creates the 'super-admin' role in Spatie and assigns it
 * to all existing admin users so they can access admin routes.
 */
return new class extends Migration
{
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $guardName = config('auth.defaults.guard', 'web');

        // Create the 'super-admin' role (with hyphen) if it doesn't exist
        $superAdminHyphen = Role::firstOrCreate([
            'name' => 'super-admin',
            'guard_name' => $guardName,
        ]);

        // Give it all permissions (same as super_admin)
        $superAdminHyphen->givePermissionTo(
            \Spatie\Permission\Models\Permission::where('guard_name', $guardName)->pluck('name')->toArray()
        );

        // Also create 'admin' role if missing
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => $guardName]);

        // Find all users who have super_admin role OR have role column = 'super_admin' / 'super-admin' / 'admin'
        $adminUsers = \App\Models\User::where(function ($q) {
            $q->whereIn('role', ['super_admin', 'super-admin', 'admin']);
        })->get();

        foreach ($adminUsers as $user) {
            // Assign the Spatie 'super-admin' role (what routes check for)
            if (!$user->hasRole('super-admin')) {
                $user->assignRole('super-admin');
            }
            // Also ensure they have super_admin for backward compatibility
            if (!$user->hasRole('super_admin')) {
                $user->assignRole('super_admin');
            }
        }

        // Clear permission cache
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Don't remove the role on rollback as it could break access
    }
};
