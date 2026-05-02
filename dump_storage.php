<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$storage = DB::table('image_storage')->limit(5)->get();
echo json_encode($storage->toArray(), JSON_PRETTY_PRINT);
