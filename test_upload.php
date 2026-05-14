<?php

$ch = curl_init();

$postData = [
    'title' => 'Test Image',
    'description' => 'Testing watermark upload',
    'privacy' => 'public',
    'allow_download' => '1',
    'watermark_on_download' => '1',
    'watermark_font_size' => '150',
    'watermark_opacity' => '50',
    'watermark_color' => 'FF0000',
    'watermark_type' => 'text',
    'watermark_text' => 'Ahmad Hrob Test',
];

// Create a dummy image
file_put_contents('dummy.jpg', 'fake image data');
$cfile = new CURLFile('dummy.jpg', 'image/jpeg', 'dummy.jpg');
$postData['images[]'] = $cfile;

// Get a token or just test the logic inside the app?
// Better to test via artisan tinker so we don't need auth.
