<?php
$log = file_get_contents('/var/www/html/storage/logs/laravel.log');
echo substr($log, strrpos($log, 'production.ERROR'));
