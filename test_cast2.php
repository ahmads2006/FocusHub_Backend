<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$token = 'my_raw_token_123';
$data = ['token' => $token];
$link = new \App\Models\SharedLink($data);
$link->token = $token;

var_dump($link->toArray());
