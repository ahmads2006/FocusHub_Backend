$IP = "64.226.110.227"
$KEY = "digitalocean.k3/id_rsa"
$REMOTE_PATH = "/var/www/opalshot"

function upload_file($local, $remote) {
    scp -i $KEY $local "root@${IP}:${remote}"
}

Write-Host "Updating User.php..."
upload_file "opalshot/app/Models/User.php" "$REMOTE_PATH/app/Models/User.php"

Write-Host "Updating ProfileController.php..."
upload_file "opalshot/app/Http/Controllers/Api/V1/ProfileController.php" "$REMOTE_PATH/app/Http/Controllers/Api/V1/ProfileController.php"

Write-Host "Fixing storage link in Docker..."
ssh -i $KEY root@$IP "docker exec opalshot-app-1 rm -f public/storage"
ssh -i $KEY root@$IP "docker exec opalshot-app-1 php artisan storage:link"

Write-Host "Restarting containers to clear cache..."
ssh -i $KEY root@$IP "cd $REMOTE_PATH && docker-compose -f docker-compose.prod.yml restart app php-worker php-scheduler reverb"

Write-Host "Deployment complete."
