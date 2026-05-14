#!/bin/bash
set -e  # Detiene el script si cualquier comando falla

DEPLOY_PATH="/var/www/sgth"
BACKEND_PATH="${DEPLOY_PATH}/sgth-backend"

echo "Iniciando despliegue SGTH..."

cd ${DEPLOY_PATH}
git pull origin main

cd ${BACKEND_PATH}
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart

echo "Deploy completado exitosamente - $(date)"
