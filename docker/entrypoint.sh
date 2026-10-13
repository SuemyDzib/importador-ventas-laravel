#!/bin/sh
set -e

# El disco de Render es temporal: en cada arranque la demo empieza desde cero.
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs
touch database/database.sqlite

php artisan migrate --force
php artisan demo:cargar

php artisan config:cache
php artisan route:cache
php artisan view:cache

chown -R www-data:www-data storage bootstrap/cache database

exec apache2-foreground
