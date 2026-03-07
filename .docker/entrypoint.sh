#!/bin/sh
set -e

# git safe directory
git config --global --add safe.directory /var/www/html

# Copy .env if not exists
if [ ! -f .env ]; then
    echo "Creating .env from .env.example..."
    cp .env.example .env
fi

# Install dependencies if vendor is missing
if [ ! -d "vendor" ]; then
    echo "Vendor directory not found. Running composer install..."
    composer install --no-interaction --prefer-dist --optimize-autoloader
fi

# Build assets if missing
if [ ! -d "public/build" ]; then
    echo "Frontend assets not found. Building..."
    npm install
    npm run build
fi

# 1. Crear estructura de carpetas interna (Evita errores de "Folder not found")
mkdir -p storage/framework/cache/data \
         storage/framework/sessions \
         storage/framework/views \
         storage/app/public \
         storage/logs \
         bootstrap/cache

# 2. Ajuste masivo de permisos (0775)
# Esto cubre: vendor, storage, public y bootstrap/cache
echo "Ajustando permisos a 775 en carpetas críticas..."
chmod -R 775 vendor storage public bootstrap/cache

# 3. Ajuste masivo de dueño (www-data)
# Para que el servidor web (PHP/Nginx) pueda escribir sin pedir permiso
echo "Cambiando propietario a www-data..."
chown -R www-data:www-data vendor storage public bootstrap/cache

# Wait for MySQL to be ready
echo "Waiting for database..."
until nc -z db 3306; do
  sleep 1
done
echo "Database is ready!"

# Generate key if not set
if [ -z "$(grep APP_KEY .env | cut -d '=' -f2)" ]; then
    echo "Generating app key..."
    php artisan key:generate
fi

# Run migrations (skip schema load if it fails)
echo "Running migrations..."
php artisan migrate --force --schema-path=/dev/null || php artisan migrate --force

# Actualizar Blacklist de Emails (Persistencia ante rebuilds/limpieza de caché)
echo "Actualizando Blacklist de Emails..."
php artisan auto:email-blacklist-update

# Run Octane or PHP-FPM
echo "Starting PHP-FPM..."

exec php-fpm
