#!/bin/bash

[ -f .env ] || cp .env.example .env

php artisan key:generate --force

# Skip cache:clear since it's failing
php artisan config:clear
# php artisan cache:clear  # Comment this out
php artisan route:clear
php artisan view:clear

# Don't cache config until database issue is resolved
# php artisan config:cache

mkdir -p /app/storage

php -S 0.0.0.0:${PORT:-8080} -t public