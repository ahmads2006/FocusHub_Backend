<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Album;
use Illuminate\Http\UploadedFile;
use App\Services\Core\ImageService;
use Illuminate\Support\Facades\Redis;

// Find a user and an album
$user = User::first();
if (!$user) {
    echo "No user found.\n";
    exit;
}

// Create a dummy uploaded file (use oar2.jpg if it exists to test Gore, or any other image)
$testImagePath = storage_path('app/quarantine/rejected_69bebaf8d09ae_images_2026_03_fallback_69bb369c1d981_oar2.jpg');
if (!file_exists($testImagePath)) {
    // try finding any jpg
    $files = glob(public_path('images/*/*.jpg')) ?: glob(storage_path('app/secure_uploads/*.jpg'));
    if (!empty($files)) {
        $testImagePath = $files[0];
    } else {
        echo "No test image found.\n";
        exit;
    }
}

echo "Testing Pipeline with Image: " . basename($testImagePath) . "\n";

// Clear standard logs to see clean output
file_put_contents(storage_path('logs/laravel.log'), '');

// We will test ImageIntelligenceService directly for the tagging part, and ContentSafetyService for the safety part
$safetyService = app(\App\Services\AI\ContentSafetyService::class);
$uploadedFile = new UploadedFile($testImagePath, basename($testImagePath), 'image/jpeg', null, true);

echo "\n--- STAGE 1: SAFETY GATEKEEPER ---\n";
try {
    $safetyResult = $safetyService->validate($uploadedFile);
    echo "Status: " . $safetyResult['status'] . "\n";
    echo "Is Sensitive: " . ($safetyResult['is_sensitive'] ? 'true' : 'false') . "\n";
    echo "Is Visible: " . ($safetyResult['is_visible'] ? 'true' : 'false') . "\n";
    echo "Reason: " . $safetyResult['reason'] . "\n";
    echo "Driver: " . $safetyResult['driver'] . "\n";
    if (isset($safetyResult['metadata']['raw_results']['_gore_score'])) {
        echo "Gore Score: " . $safetyResult['metadata']['raw_results']['_gore_score'] . "\n";
    }
} catch (Exception $e) {
    echo "Safety Gatekeeper Failed: " . $e->getMessage() . "\n";
}

echo "\n--- LARAVEL LOGS (Safety) ---\n";
echo trim(file_get_contents(storage_path('logs/laravel.log'))) . "\n";

