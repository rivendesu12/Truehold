#!/usr/bin/env bash
# Deploy truehold.co.uk. Run on the server, from the app directory.
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/truehold}"
cd "$APP_DIR"

echo "==> Pulling latest code"
git pull --ff-only origin main

echo "==> Installing PHP dependencies (production)"
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

echo "==> Running migrations"
# Additive only. Never use migrate:fresh / migrate:refresh here.
php artisan migrate --force

echo "==> Rebuilding caches"
php artisan optimize:clear
php artisan optimize

echo "==> Fixing permissions"
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache

echo "==> Reloading PHP-FPM"
systemctl reload php8.4-fpm

echo "==> Health check"
curl -fsS -o /dev/null -w "  /up -> %{http_code}\n" http://127.0.0.1/up || {
  echo "  HEALTH CHECK FAILED"; exit 1;
}

echo "==> Done"
