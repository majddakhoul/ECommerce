#!/bin/sh
set -e

php artisan config:clear
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link 2>/dev/null || true

exec php artisan serve --host=0.0.0.0 --port=8000
