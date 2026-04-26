#!/bin/bash
echo "Starting 5 Queue Workers to process heavy Image operations asynchronously..."
echo "Press Ctrl+C to stop all workers."

# Run 5 workers in the background
php artisan queue:work --daemon --tries=3 --timeout=180 &
php artisan queue:work --daemon --tries=3 --timeout=180 &
php artisan queue:work --daemon --tries=3 --timeout=180 &
php artisan queue:work --daemon --tries=3 --timeout=180 &
php artisan queue:work --daemon --tries=3 --timeout=180 &

# Wait for all background jobs to keep the script running
wait
