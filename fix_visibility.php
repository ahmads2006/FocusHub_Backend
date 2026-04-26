<?php
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$disk = \Illuminate\Support\Facades\Storage::disk('s3');
$files = $disk->allFiles('photos');
echo count($files) . " files found\n";
foreach ($files as $f) {
    try {
        $disk->setVisibility($f, 'public');
        echo "Fixed: $f\n";
    } catch (\Exception $e) {
        echo "Error on $f: " . $e->getMessage() . "\n";
    }
}
echo "Done!\n";
