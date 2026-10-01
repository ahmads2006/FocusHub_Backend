<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $guardName = config('auth.defaults.guard', 'web');

        // ─── الصلاحيات ─────────────────────────────────────────
        $permissions = [
            'access-admin-dashboard',
            'manage-users',
            'ban-users',
            'delete-any-content',
            'change-content-privacy',
            'view-activity-logs',
            'upgrade-user-roles',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => $guardName]);
        }

        // ─── الأدوار ───────────────────────────────────────────
        // Create both super_admin and super-admin roles for compatibility
        // Routes use 'role:super-admin' (with hyphen), so we need both forms
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => $guardName]);
        $superAdmin->givePermissionTo(Permission::all());

        $superAdminHyphen = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => $guardName]);
        $superAdminHyphen->givePermissionTo(Permission::all());

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => $guardName]);
        Role::firstOrCreate(['name' => 'photographer', 'guard_name' => $guardName]);
        Role::firstOrCreate(['name' => 'editor', 'guard_name' => $guardName]);
        Role::firstOrCreate(['name' => 'user', 'guard_name' => $guardName]);
    }
}
