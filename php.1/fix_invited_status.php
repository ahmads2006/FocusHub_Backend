<?php
// Fix: Change all 'invited' statuses to 'accepted' in album_user pivot table
// This is a one-time migration fix

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$count = DB::table('album_user')
    ->where('status', 'invited')
    ->update(['status' => 'accepted']);

echo "Fixed {$count} records: changed status from 'invited' to 'accepted'\n";
