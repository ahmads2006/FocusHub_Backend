#!/bin/bash

# OpticVault Ultimate Setup Script (v6.0)
# Purpose: Automate the entire infrastructure setup with a single command.

set -e

# Colors for output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${BLUE}🛡️ Starting OpticVault Plug-and-Play Initialization...${NC}"

# 1. Environment Detection & Binary Check
echo -e "\n${YELLOW}[1/5] Checking System Environment...${NC}"
if [ -f /etc/debian_version ]; then
    echo "   Detected Debian/Ubuntu environment."
    # Optional: Attempt to install dependencies if sudo is available
fi

REQUIRED_BINARIES=("php" "composer" "npm" "git")
for bin in "${REQUIRED_BINARIES[@]}"; do
    if ! command -v "$bin" &> /dev/null; then
        echo -e "   [ERROR] $bin is not installed. Please install it to continue."
        exit 1
    fi
    echo "   [OK] Found $bin"
done

# 2. Dependency Installation
echo -e "\n${YELLOW}[2/5] Installing Dependencies (Backend & Frontend)...${NC}"
composer install --no-interaction
npm install

# 3. Environment Configuration
echo -e "\n${YELLOW}[3/5] Configuring Environment...${NC}"
if [ ! -f .env ]; then
    cp .env.example .env
    php artisan key:generate
    echo "   [CREATED] .env and generated APP_KEY"
else
    echo "   [EXISTS]  .env file"
fi

# 4. Secure Infrastructure Setup
echo -e "\n${YELLOW}[4/5] Building Secure Virtual Vault...${NC}"
php artisan optic:init
php artisan migrate --force

# 5. Final Optimization
echo -e "\n${YELLOW}[5/5] Optimizing System...${NC}"
npm run build
php artisan config:cache
php artisan route:cache
php artisan storage:link
php artisan optic:prune

echo -e "\n${GREEN}✨ OpticVault is now successfully deployed and secured.${NC}"
echo -e "${BLUE}📡 Run 'php artisan serve' to start the engine.${NC}"
mkdir -p storage/app/private/vault
chmod -R 775 storage/app/private

if ! php -m | grep -qi 'exif'; then
    echo -e "${YELLOW}[WARNING] PHP EXIF extension is missing. Please enable it in php.ini${NC}"
fi


