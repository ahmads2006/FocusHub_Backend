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
    }
}
