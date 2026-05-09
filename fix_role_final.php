<?php
require '/var/www/html/vendor/autoload.php';
$app = require_once '/var/www/html/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$email = 'hrobahmad9@gmail.com';
$roleName = 'super-admin';

$user = App\Models\User::where('email', $email)->first();
if ($user) {
    $role = Spatie\Permission\Models\Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
    $user->syncRoles([$roleName]);
    Illuminate\Support\Facades\DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('super_admin', 'super-admin', 'admin', 'user', 'photographer') DEFAULT 'user'");
    Illuminate\Support\Facades\DB::table('users')->where('id', $user->id)->update(['role' => $roleName]);
    echo "DONE: Promoted to $roleName and fixed ENUM.\n";
} else {
    echo "ERROR: User not found.\n";
}
