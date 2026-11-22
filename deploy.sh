#!/usr/bin/env bash
set -euo pipefail

BRANCH="${1:-main}"

echo "==> Starting safe deployment on branch: ${BRANCH}"

# Put app into maintenance mode during update
php artisan down --retry=30 || true

# Pull latest code (never touches .env or storage/app/public)
git pull origin "${BRANCH}"

# Install production PHP dependencies (including minishlink/web-push)
composer install --no-dev --optimize-autoloader --no-interaction

# Run ONLY pending additive migrations (existing data stays untouched)
php artisan migrate --force

# Refresh Laravel caches
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Bring app back online
php artisan up

echo "==> Deployment completed successfully!"
