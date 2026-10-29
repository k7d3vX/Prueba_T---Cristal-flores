#!/usr/bin/env bash
set -e
cd /var/www

[ -f .env ] || cp .env.example .env
grep -q '^APP_KEY=base64' .env || php artisan key:generate --force

mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache
chmod -R 777 storage bootstrap/cache

# Migra y carga el día de prueba (el seeder es idempotente)
php artisan migrate --seed --force

exec php artisan serve --host=0.0.0.0 --port=8000
