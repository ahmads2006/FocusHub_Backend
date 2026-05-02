<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$image = \App\Models\Image::where('privacy', 'public')->latest()->first();

echo "Appends property from reflection:\n";
$reflection = new \ReflectionClass($image);
$property = $reflection->getProperty('appends');
$property->setAccessible(true);
var_dump($property->getValue($image));

echo "\nCalling getMutatedAttributes():\n";
var_dump($image->getMutatedAttributes());
