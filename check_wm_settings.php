<?php
require "/var/www/html/vendor/autoload.php";
$app = require "/var/www/html/bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$settings = App\Models\UserSetting::all();
foreach ($settings as $s) {
    echo json_encode([
        'user_id' => $s->user_id,
        'mode' => $s->watermark_mode,
        'logo' => $s->watermark_logo,
        'text' => $s->watermark_text,
    ]) . "\n";
}
