#!/usr/bin/env bash
# Deploy script untuk Hostinger shared hosting (hPanel + SSH).
# Jalankan dari root project di server: bash deploy.sh

set -e

echo "==> Pull terbaru dari origin/main"
git pull origin main

echo "==> Composer install (production)"
composer install --no-dev --optimize-autoloader --no-interaction

echo "==> Migrate database"
php artisan migrate --force

echo "==> Clear & cache config/route/view"
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

echo "==> Storage symlink (idempotent)"
php artisan storage:link || true

echo "==> Permission storage & bootstrap/cache"
chmod -R 775 storage bootstrap/cache || true

echo "==> Done. App sudah live."
