<?php
$env = file_get_contents('.env');
$env = preg_replace('/SESSION_DOMAIN=.*/', 'SESSION_DOMAIN=.opalshot.studio', $env);
if (!str_contains($env, 'SESSION_SECURE_COOKIE')) {
    $env .= "\nSESSION_SECURE_COOKIE=true";
} else {
    $env = preg_replace('/SESSION_SECURE_COOKIE=.*/', 'SESSION_SECURE_COOKIE=true', $env);
}
if (!str_contains($env, 'SESSION_SAME_SITE')) {
    $env .= "\nSESSION_SAME_SITE=none";
} else {
    $env = preg_replace('/SESSION_SAME_SITE=.*/', 'SESSION_SAME_SITE=none', $env);
}
file_put_contents('.env', $env);
echo "Env updated successfully\n";
