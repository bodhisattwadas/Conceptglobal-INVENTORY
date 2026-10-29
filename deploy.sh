#!/usr/bin/env bash
# ==============================================================================
# ConceptGlobal Inventory - Unified Post-Deployment Script
# ==============================================================================
# Usage:
#   ./deploy.sh            # Standard post-git-pull deployment
#   ./deploy.sh --pull     # Pull latest main branch from git then deploy
#   ./deploy.sh --quick    # Quick migration + cache purge only (< 2 seconds)
#   ./deploy.sh --dev      # Deploy in development mode (no-dev omitted, no route/view caching)
# ==============================================================================

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

# Text styles
BOLD='\033[1m'
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

echo -e "\n${BOLD}${BLUE}====================================================${NC}"
echo -e "${BOLD}${BLUE}   ConceptGlobal Inventory - Deployment Script      ${NC}"
echo -e "${BOLD}${BLUE}====================================================${NC}\n"

DO_PULL=false
IS_QUICK=false
IS_DEV=false

for arg in "$@"; do
    case "$arg" in
        --pull)
            DO_PULL=true
            ;;
        --quick)
            IS_QUICK=true
            ;;
        --dev)
            IS_DEV=true
            ;;
    esac
done

# Step 1: Optional Git Pull
if [ "$DO_PULL" = true ]; then
    echo -e "${YELLOW}📥 [1/8] Pulling latest code from origin main...${NC}"
    git pull origin main
else
    echo -e "${BLUE}ℹ️  [1/8] Skipping git pull (use --pull to fetch automatically)${NC}"
fi

# Step 2: Maintenance Mode (optional, graceful)
echo -e "${YELLOW}🔒 [2/8] Enabling maintenance mode...${NC}"
php artisan down || true

# Step 3: Install/Update Composer Dependencies
if [ "$IS_QUICK" = false ]; then
    echo -e "${YELLOW}📦 [3/8] Installing Composer dependencies...${NC}"
    if [ "$IS_DEV" = true ]; then
        composer install --no-interaction --prefer-dist
    else
        composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev
    fi
else
    echo -e "${BLUE}⏩ [3/8] Skipping composer install (--quick mode)${NC}"
fi

# Step 4: Run Database Migrations
echo -e "${YELLOW}🗄️  [4/8] Running database migrations...${NC}"
php artisan migrate --force

# Step 5: Complete Cache Clear (Purges Views, Routes, Config, Events, App Cache)
echo -e "${YELLOW}🧹 [5/8] Clearing all application & compiled caches...${NC}"
php artisan optimize:clear

# Step 6: Build Frontend Assets (if Node/NPM available)
if [ "$IS_QUICK" = false ] && command -v npm &> /dev/null; then
    echo -e "${YELLOW}🎨 [6/8] Building Vite frontend assets...${NC}"
    npm run build
else
    echo -e "${BLUE}⏩ [6/8] Skipping asset build (prebuilt assets used or --quick mode)${NC}"
fi

# Step 7: Production Optimization & View Precompilation
if [ "$IS_DEV" = false ] && [ "$IS_QUICK" = false ]; then
    echo -e "${YELLOW}⚡ [7/8] Caching configuration, routes, and views for production...${NC}"
    php artisan optimize
else
    echo -e "${BLUE}ℹ️  [7/8] Running without production cache (quick/dev mode)${NC}"
fi

# Step 8: Restart Queue Workers & Reset OPcache
echo -e "${YELLOW}🔄 [8/8] Restarting queue workers and resetting OPcache...${NC}"
php artisan queue:restart || true

# Reset PHP-FPM OPcache if systemctl is accessible
if command -v systemctl &> /dev/null; then
    for version in php8.3-fpm php8.2-fpm php8.4-fpm php-fpm; do
        if systemctl is-active --quiet "$version" 2>/dev/null; then
            echo -e "   Reloading $version for OPcache reset..."
            sudo systemctl reload "$version" 2>/dev/null || true
            break
        fi
    done
fi

# Bring application back up
echo -e "${YELLOW}🔓 Bringing application back online...${NC}"
php artisan up || true

echo -e "\n${BOLD}${GREEN}====================================================${NC}"
echo -e "${BOLD}${GREEN}   ✅ Deployment completed successfully!           ${NC}"
echo -e "${BOLD}${GREEN}====================================================${NC}\n"
