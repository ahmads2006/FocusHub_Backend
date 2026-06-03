$IP = "64.226.110.227"
$KEY = "digitalocean.k3/id_rsa"
$REMOTE_PATH = "/var/www/opalshot"

$SCRIPT_DIR = Split-Path -Parent $MyInvocation.MyCommand.Path
$BASE = $SCRIPT_DIR
$PROJECT_ROOT = Split-Path $SCRIPT_DIR -Parent
$KEY_PATH = Join-Path -Path $PROJECT_ROOT -ChildPath $KEY

function upload_file {
    param($local, $remote)
    $localPath = $local -replace '/', '\'
    $fullPath = Join-Path -Path $BASE -ChildPath $localPath
    if (-not (Test-Path $fullPath)) {
        throw "Local file not found: $fullPath"
    }
    Write-Host "Uploading $local"
    scp -i $KEY_PATH "$fullPath" "root@${IP}:${remote}"
    if ($LASTEXITCODE -ne 0) {
        throw "SCP failed for $local"
    }
}

Write-Host "Deploying updated likes count backend files..."

$filesToDeploy = @(
    "app/Http/Controllers/Api/ImageController.php",
    "app/Http/Controllers/Api/V1/SharedLinkController.php",
    "app/Services/AI/RecommendationEngine.php",
    "app/Models/Album.php",
    "app/Http/Controllers/Api/V1/ProfileController.php"
)

foreach ($file in $filesToDeploy) {
    upload_file $file "$REMOTE_PATH/$file"
}

Write-Host "Running migration + cache refresh inside app container..."
$remoteCmd = @"
set -e
cd $REMOTE_PATH
APP_CONTAINER=`$(docker compose -f docker-compose.prod.yml ps -q app | head -n 1)
if [ -z "`$APP_CONTAINER" ]; then
  echo "App container not found"
  exit 1
fi
docker exec `$APP_CONTAINER php artisan migrate --force
docker exec `$APP_CONTAINER php artisan optimize:clear
docker exec `$APP_CONTAINER php artisan route:clear
docker exec `$APP_CONTAINER php artisan config:clear
docker exec `$APP_CONTAINER php artisan route:list --path=api/v1/metrics
docker exec `$APP_CONTAINER php artisan route:list --path=api/v1/admin/performance
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
