$IP = "64.226.110.227"
$KEY = "digitalocean.k3/id_rsa"
$REMOTE_PATH = "/var/www/opalshot"

function upload_file($local, $remote) {
    scp -i $KEY $local "root@${IP}:${remote}"
}

Write-Host "Updating Core Models..."
upload_file "opalshot/app/Models/User.php" "$REMOTE_PATH/app/Models/User.php"
upload_file "opalshot/app/Models/SharedLink.php" "$REMOTE_PATH/app/Models/SharedLink.php"

Write-Host "Updating Logic & Controllers..."
upload_file "opalshot/app/Http/Controllers/Api/ImageController.php" "$REMOTE_PATH/app/Http/Controllers/Api/ImageController.php"
upload_file "opalshot/app/Http/Controllers/Api/V1/SharedLinkController.php" "$REMOTE_PATH/app/Http/Controllers/Api/V1/SharedLinkController.php"
upload_file "opalshot/app/Http/Controllers/Api/V1/MongoChatController.php" "$REMOTE_PATH/app/Http/Controllers/Api/V1/MongoChatController.php"
upload_file "opalshot/app/Events/MessageSent.php" "$REMOTE_PATH/app/Events/MessageSent.php"
upload_file "opalshot/app/Http/Middleware/ValidateSharedLink.php" "$REMOTE_PATH/app/Http/Middleware/ValidateSharedLink.php"
upload_file "opalshot/app/Services/Security/SharedLinkService.php" "$REMOTE_PATH/app/Services/Security/SharedLinkService.php"
upload_file "opalshot/app/Http/Controllers/Api/V1/GalleryController.php" "$REMOTE_PATH/app/Http/Controllers/Api/V1/GalleryController.php"
upload_file "opalshot/app/Http/Controllers/Web/DownloadController.php" "$REMOTE_PATH/app/Http/Controllers/Web/DownloadController.php"
upload_file "opalshot/app/Services/Core/AssetDeliveryService.php" "$REMOTE_PATH/app/Services/Core/AssetDeliveryService.php"

Write-Host "Updating Policies & Notifications..."
upload_file "opalshot/app/Policies/ImagePolicy.php" "$REMOTE_PATH/app/Policies/ImagePolicy.php"
upload_file "opalshot/app/Policies/AlbumPolicy.php" "$REMOTE_PATH/app/Policies/AlbumPolicy.php"
upload_file "opalshot/app/Notifications/SharedLinkLeakDetected.php" "$REMOTE_PATH/app/Notifications/SharedLinkLeakDetected.php"

Write-Host "Updating Bootstrap..."
upload_file "opalshot/bootstrap/app.php" "$REMOTE_PATH/bootstrap/app.php"
upload_file "opalshot/routes/channels.php" "$REMOTE_PATH/routes/channels.php"

Write-Host "Updating Kernel..."
upload_file "opalshot/app/Http/Kernel.php" "$REMOTE_PATH/app/Http/Kernel.php"

Write-Host "Updating Security & Logs..."
upload_file "opalshot/app/Http/Middleware/RequestLogger.php" "$REMOTE_PATH/app/Http/Middleware/RequestLogger.php"
upload_file "opalshot/app/Helpers/SecurityHelper.php" "$REMOTE_PATH/app/Helpers/SecurityHelper.php"

Write-Host "Updating Image Policy..."
upload_file "opalshot/app/Policies/ImagePolicy.php" "$REMOTE_PATH/app/Policies/ImagePolicy.php"

Write-Host "Updating Album Policy..."
upload_file "opalshot/app/Policies/AlbumPolicy.php" "$REMOTE_PATH/app/Policies/AlbumPolicy.php"

Write-Host "Updating ProfileController..."
Write-Host "Updating ProfileController..."
upload_file "opalshot/app/Http/Controllers/Api/V1/ProfileController.php" "$REMOTE_PATH/app/Http/Controllers/Api/V1/ProfileController.php"

Write-Host "Updating User Profile..."
upload_file "opalshot/app/Models/User.php" "$REMOTE_PATH/app/Models/User.php"

Write-Host "Uploading new migrations..."
upload_file "opalshot/database/migrations/2026_05_14_181300_add_token_hash_to_shared_links.php" "$REMOTE_PATH/database/migrations/2026_05_14_181300_add_token_hash_to_shared_links.php"

Write-Host "Running migrations on server..."
ssh -i $KEY root@$IP "docker exec opalshot-app-1 php artisan migrate --force"

Write-Host "Restarting containers to clear cache..."
ssh -i $KEY root@$IP "cd $REMOTE_PATH && docker-compose -f docker-compose.prod.yml restart app php-worker php-scheduler reverb"

Write-Host "Deployment complete."
