<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$rows = DB::table('image_settings')
    ->select('image_id','watermark_text','watermark_type','watermark_on_download','watermark_font_size','watermark_opacity','watermark_color')
    ->orderBy('id', 'desc')
    ->take(3)
    ->get();

foreach ($rows as $r) {
    echo "Image #{$r->image_id}: text='{$r->watermark_text}' type='{$r->watermark_type}' fs={$r->watermark_font_size} op={$r->watermark_opacity} color={$r->watermark_color}\n";
}
