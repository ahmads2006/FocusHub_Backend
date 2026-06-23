<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        $user = User::firstOrCreate(
            ['email' =>   'hnntatna@gmail.com'],
            ['name' => 'Test User', 'password' => Hash::make('password'), 'is_verified' => false]
        );
        $user->assignRole('user');

        $admin = User::firstOrCreate(
            ['email' => 'hrobahmad9@gmail.com'],
            ['name' => 'Super Admin', 'password' => Hash::make('Ahmad'), 'is_verified' => true]
        );
        $admin->role = 'super_admin'; // التأكد من تعيين العمود رول
        $admin->save();
        $admin->assignRole('super_admin');

        // Seed default system settings
        \App\Models\SystemSetting::firstOrCreate(
            ['key' => 'site_offline'],
            ['value' => 'false', 'type' => 'boolean', 'description' => 'Whether the entire site is offline for maintenance']
        );
        \App\Models\SystemSetting::firstOrCreate(
            ['key' => 'site_offline_message'],
            ['value' => 'الموقع قيد الصيانة حالياً. سنعود قريباً!', 'type' => 'string', 'description' => 'Maintenance message shown to users']
        );
        \App\Models\SystemSetting::firstOrCreate(
            ['key' => 'site_offline_countdown'],
            ['value' => null, 'type' => 'string', 'description' => 'Estimated date/time when site will be back online']
        );
        \App\Models\SystemSetting::firstOrCreate(
            ['key' => 'allowed_ips'],
            ['value' => '[]', 'type' => 'json', 'description' => 'IP addresses allowed to bypass offline mode']
        );
        \App\Models\SystemSetting::firstOrCreate(
            ['key' => 'disabled_features'],
            ['value' => '[]', 'type' => 'json', 'description' => 'List of currently disabled features/modules']
        );
    }
}
