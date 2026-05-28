#!/bin/bash
set -e

# Fix storage permissions on every container start
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true

# Create required storage directories if missing
mkdir -p /var/www/html/storage/framework/{sessions,views,cache}
mkdir -p /var/www/html/storage/logs
mkdir -p /var/www/html/storage/app/public

# Clear and cache config for production
php artisan config:cache 2>/dev/null || true
php artisan route:cache 2>/dev/null || true
php artisan view:cache 2>/dev/null || true

# Run database migrations (safe in production with --force)
php artisan migrate --force 2>/dev/null || true

# Create storage symlink if not exists
php artisan storage:link 2>/dev/null || true

# Execute the main container command (e.g. apache2-foreground)
exec "$@"
