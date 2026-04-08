<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = App\Models\User::where('email', 'hrobahmad9@gmail.com')->first();
if ($user) {
    echo "USER_FOUND\n";
    echo "Role: [" . $user->role . "]\n";
    echo "Is Super Admin (binary): " . ($user->role === 'super_admin' ? 'Yes' : 'No') . "\n";
    echo "Role type: " . gettype($user->role) . "\n";
    echo "Roles (Spatie): " . json_encode($user->getRoleNames()) . "\n";
} else {
    echo "USER_NOT_FOUND\n";
}
