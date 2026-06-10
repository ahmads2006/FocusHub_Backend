<?php
/**
 * Test Script: Verify Yellow (pending_review) Moderation Path
 * 
 * This script simulates a file upload through ContentSafetyService
 * to verify that the safety verdict is correctly returned and would
 * be stored as pending_review if the image triggers weapon/nudity thresholds.
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\AI\ContentSafetyService;
use Illuminate\Http\UploadedFile;

$safety = app(ContentSafetyService::class);

$testFiles = [
    '/tmp/test_tactical_knife.png'  => 'Tactical Knife',
    '/tmp/test_handgun.png'         => 'Handgun',
    '/tmp/test_male_shirtless.png'  => 'Shirtless Male Athlete',
    '/tmp/test_swimsuit.png'        => 'Swimsuit',
];

echo "=== MODERATION SYSTEM TEST ===\n\n";

foreach ($testFiles as $path => $label) {
    if (!file_exists($path)) {
        echo "⏩ SKIP: {$label} — file not found at {$path}\n\n";
        continue;
    }

    echo "🔍 Testing: {$label}\n";
    echo "   File: {$path}\n";
    echo "   Size: " . number_format(filesize($path)) . " bytes\n";
    
    // Create an UploadedFile from the test file
    $file = new UploadedFile(
        $path,
        basename($path),
        mime_content_type($path),
        null,
        true  // test mode
    );

    try {
        $result = $safety->check($file);
        
        $statusEmoji = match($result['status']) {
            'approved' => '🟢',
            'pending_review' => '🟡',
            'rejected' => '🔴',
            default => '⚪',
        };
        
        echo "   Status: {$statusEmoji} {$result['status']}\n";
        echo "   Sensitive: " . ($result['is_sensitive'] ? 'YES' : 'NO') . "\n";
        echo "   Visible: " . ($result['is_visible'] ? 'YES' : 'NO') . "\n";
        echo "   Reason: " . ($result['reason'] ?? 'N/A') . "\n";
        echo "   Driver: " . ($result['driver'] ?? 'N/A') . "\n";
        
        // Show raw safety scores if available
        $metadata = $result['metadata'] ?? [];
        $rawResults = $metadata['raw_results'] ?? $metadata;
        if (isset($rawResults['_safety_verdict'])) {
            echo "   AI Verdict: {$rawResults['_safety_verdict']}\n";
        }
        if (isset($rawResults['_sensitivity_reasons'])) {
            echo "   AI Reasons: " . implode(', ', $rawResults['_sensitivity_reasons']) . "\n";
        }
        if (isset($rawResults['_gore_score'])) {
            echo "   Gore Score: {$rawResults['_gore_score']}\n";
        }
        // Nudity scores
        if (isset($rawResults['nudity'])) {
            $n = $rawResults['nudity'];
            echo "   Nudity Safe: " . ($n['safe'] ?? $n['none'] ?? 'N/A') . "\n";
            echo "   Nudity Risk: " . max(
                $n['sexual_activity'] ?? 0,
                $n['sexual_display'] ?? 0,
                $n['erotica'] ?? 0,
                $n['suggestive'] ?? 0
            ) . "\n";
        }
        // Weapon scores
        if (isset($rawResults['weapon'])) {
            echo "   Weapon Data: " . json_encode($rawResults['weapon']) . "\n";
        }
        
        echo "   Full Result: " . json_encode($result, JSON_PRETTY_PRINT) . "\n";
    } catch (\Exception $e) {
        echo "   ❌ ERROR: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
}

echo "=== TEST COMPLETE ===\n";
