<?php
require '/var/www/html/vendor/autoload.php';
$app = require_once '/var/www/html/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$count = DB::table('album_user')
    ->where('status', 'invited')
    ->update(array('status' => 'accepted'));

echo "Fixed: " . $count . " records changed from invited to accepted\n";
