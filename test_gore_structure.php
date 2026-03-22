<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$data = json_decode('{
    "gore": {
        "type": { "animated": 0.001, "fake": 0.001, "real": 0.99 },
        "prob": 0.99,
        "classes": {
            "unconscious": 0.001, "other": 0.001, "very_bloody": 0.99,
            "serious_injury": 0.99, "corpse": 0.99
        }
    }
}', true);

$goreScore = 0;
if (isset($data['gore']['classes'])) {
    $goreScore = max(
        $data['gore']['classes']['very_bloody'] ?? 0,
        $data['gore']['classes']['slightly_bloody'] ?? 0,
        $data['gore']['classes']['corpse'] ?? 0,
        $data['gore']['classes']['serious_injury'] ?? 0,
        $data['gore']['classes']['superficial_injury'] ?? 0,
        $data['gore']['classes']['body_organ'] ?? 0
    );
}
echo "Calculated Gore Score: " . $goreScore . "\n";
