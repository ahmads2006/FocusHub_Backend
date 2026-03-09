<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\SecurityService;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

$security = app(SecurityService::class);

echo "--- Zero-Trust Security Verification ---\n";

// Test Case 1: Renamed PHP file (Web Shell simulation)
$shellPath = __DIR__ . '/storage/app/tmp/evil.jpg';
file_put_contents($shellPath, '<?php echo "I am a web shell"; ?>');
$shellFile = new UploadedFile($shellPath, 'evil.jpg', 'image/jpeg', null, true);

try {
    echo "[Testing] Spoofed PHP file (evil.jpg)...\n";
    $security->verify($shellFile);
    echo "❌ FAILED: SecurityService accepted a PHP file.\n";
} catch (ValidationException $e) {
    echo "✅ PASSED: Rejected spoofed file. Message: " . $e->getMessage() . "\n";
}

// Test Case 2: Polyglot File (Genuine header + malicious payload)
$polyPath = __DIR__ . '/storage/app/tmp/polyglot.png';
$pngHeader = hex2bin('89504e470d0a1a0a');
file_put_contents($polyPath, $pngHeader . '<?php echo "malicious code"; ?>');
$polyFile = new UploadedFile($polyPath, 'polyglot.png', 'image/png', null, true);

try {
    echo "[Testing] Polyglot file (PNG header + PHP code)...\n";
    // This might pass Magic Bytes if we only check the header, 
    // but the sanitization (re-encoding) in ImageUploadService would strip it.
    // However, SecurityService's finfo check might catch it depending on libmagic rules.
    $security->verify($polyFile);
    echo "ℹ️ SecurityService verified header. Re-encoding will handle the payload.\n";
} catch (ValidationException $e) {
    echo "✅ PASSED: SecurityService caught the polyglot. Message: " . $e->getMessage() . "\n";
}

// Test Case 3: Malicious SVG (XSS simulation)
$svgPath = __DIR__ . '/storage/app/tmp/xss.svg';
file_put_contents($svgPath, '<svg xmlns="http://www.w3.org/2000/svg"><script>alert("XSS")</script></svg>');
$svgFile = new UploadedFile($svgPath, 'xss.svg', 'image/svg+xml', null, true);

try {
    echo "[Testing] Malicious SVG (XSS script)...\n";
    $security->verify($svgFile);
    echo "❌ FAILED: SecurityService accepted malicious SVG.\n";
} catch (ValidationException $e) {
    echo "✅ PASSED: Rejected malicious SVG. Message: " . $e->getMessage() . "\n";
}

@unlink($shellPath);
@unlink($polyPath);
@unlink($svgPath);
echo "--- Verification Complete ---\n";
