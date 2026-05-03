<?php

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Image;

$img = new Image();
$ref = new ReflectionClass($img);
echo "Class file: " . $ref->getFileName() . "\n";

$prop = $ref->getProperty('appends');
$prop->setAccessible(true);
echo "Appends count: " . count($prop->getValue($img)) . "\n";
echo "Appends content:\n";
print_r($prop->getValue($img));
