$IP = "64.226.110.227"
$KEY = "digitalocean.k3/id_rsa"
$REMOTE_PATH = "/var/www/opalshot"

$SCRIPT_DIR = Split-Path -Parent $MyInvocation.MyCommand.Path
$BASE = $SCRIPT_DIR
$PROJECT_ROOT = Split-Path $SCRIPT_DIR -Parent
$KEY_PATH = Join-Path -Path $PROJECT_ROOT -ChildPath $KEY

Write-Host "Archiving updated backend files..."
$filesToDeploy = @(
    "app/Http/Controllers/Api/V1/SettingsController.php",
    "app/Http/Controllers/Api/V1/WatermarkController.php",
    "app/Http/Controllers/Web/AssetAccessController.php",
    "app/Services/Core/ImageKitService.php",
    "app/Services/Core/ImageService.php",
    "app/Services/Security/SecureShieldService.php",
    "app/Services/Security/SteganographyService.php",
    "routes/api.php",
    "app/Http/Controllers/Web/DownloadController.php",
    "app/Http/Controllers/Api/ImageController.php",
    "app/Http/Controllers/Web/ProfileController.php",
    "app/Http/Controllers/Api/V1/ProfileController.php",
    "app/Http/Controllers/Api/V1/MongoChatController.php",
    "app/Http/Controllers/Api/V1/GroupChatController.php",
    "app/Services/MongoDBService.php",
    "app/Jobs/ProcessMongoOutbox.php",
    "app/Models/Conversation.php",
    "app/Models/Album.php",
    "app/Http/Controllers/Api/V1/SharedLinkController.php",
    "app/Http/Controllers/Api/V1/AlbumController.php",
    "app/Services/AI/RecommendationEngine.php",
    "app/Http/Controllers/Api/AlbumUploadController.php",
    "app/Jobs/ExtractArchiveJob.php",
    "app/Jobs/ModerateImageJob.php",
    "app/Jobs/ProcessImageJob.php"
)

if (Test-Path "deploy.tar.gz") { Remove-Item "deploy.tar.gz" }
& tar -czf deploy.tar.gz $filesToDeploy
if ($LASTEXITCODE -ne 0) {
    throw "Failed to create tar.gz archive"
}

Write-Host "Uploading archive to server..."
scp -i $KEY_PATH deploy.tar.gz root@${IP}:${REMOTE_PATH}/
if ($LASTEXITCODE -ne 0) {
    throw "SCP failed to upload archive"
}

Write-Host "Extracting archive on remote server..."
ssh -i $KEY_PATH root@$IP "cd $REMOTE_PATH && tar -xzf deploy.tar.gz && rm deploy.tar.gz"
if ($LASTEXITCODE -ne 0) {
    throw "Failed to extract archive on remote server"
}

# Clean up local archive
Remove-Item "deploy.tar.gz"

Write-Host "Refreshing Laravel cache inside app container..."
$remoteCmd = @"
set -e
cd $REMOTE_PATH
APP_CONTAINER=`$(docker compose -f docker-compose.prod.yml ps -q app | head -n 1)
if [ -z "`$APP_CONTAINER" ]; then
  echo "App container not found"
  exit 1
fi
docker exec `$APP_CONTAINER php artisan optimize:clear
docker exec `$APP_CONTAINER php artisan config:clear
docker exec `$APP_CONTAINER php artisan route:clear
docker exec `$APP_CONTAINER php artisan view:clear
docker exec `$APP_CONTAINER php artisan migrate --force
"@

ssh -i $KEY_PATH root@$IP $remoteCmd
if ($LASTEXITCODE -ne 0) {
    throw "Remote migration/validation command failed."
}

Write-Host "Restarting worker/scheduler/reverb services..."
ssh -i $KEY_PATH root@$IP "cd $REMOTE_PATH && docker compose -f docker-compose.prod.yml restart php-worker php-scheduler reverb"
if ($LASTEXITCODE -ne 0) {
    throw "Service restart failed."
}

Write-Host "✅ Backend deployment completed successfully."
