<?php
$envFile = '.env';
$content = file_get_contents($envFile);
$newContent = preg_replace('/IMAGEKIT_URL_ENDPOINT=https:\/\/assets\.opalshot\.studio\//', 'IMAGEKIT_URL_ENDPOINT=https://ik.imagekit.io/OPTICVAULT/', $content);
file_put_contents($envFile, $newContent);
echo "Updated .env successfully\n";
