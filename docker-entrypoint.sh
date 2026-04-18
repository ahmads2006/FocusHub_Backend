#!/bin/bash
set -e

# ──────────────────────────────────────────────────────
# OpalShot Production Entrypoint
# Ensures storage directories and permissions are correct
# before Apache starts, regardless of volume mount state.
# ──────────────────────────────────────────────────────

echo "[Entrypoint] Ensuring storage directory structure..."

# Create all required Laravel storage subdirectories
mkdir -p /var/www/html/storage/logs
mkdir -p /var/www/html/storage/framework/cache/data
mkdir -p /var/www/html/storage/framework/sessions
mkdir -p /var/www/html/storage/framework/views
mkdir -p /var/www/html/storage/framework/testing
mkdir -p /var/www/html/storage/app/public
mkdir -p /var/www/html/storage/app/fonts

# Fix ownership recursively (www-data = UID 33 in Debian Apache)
chown -R www-data:www-data /var/www/html/storage
chown -R www-data:www-data /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage
chmod -R 775 /var/www/html/bootstrap/cache

# Ensure the log file exists and is writable
touch /var/www/html/storage/logs/laravel.log
chown www-data:www-data /var/www/html/storage/logs/laravel.log
chmod 664 /var/www/html/storage/logs/laravel.log

echo "[Entrypoint] Storage ready. Starting application..."

# Execute the original CMD (apache2-foreground)
exec "$@"
