<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$count = DB::table('notifications')->count();
echo "Total Notifications: " . $count . "\n";
if ($count > 0) {
    $first = DB::table('notifications')->first();
    print_r($first);
}
