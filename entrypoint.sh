#!/bin/bash
set -e
cd /var/www/html

# Ensure .env exists
[ -f .env ] || cp .env.example .env

# Write .env from OS environment variables (provided by Render)
{
    echo "APP_NAME=${APP_NAME:-Cartxis}"
    echo "APP_ENV=production"
    echo "APP_DEBUG=false"
    echo "APP_URL=${APP_URL:-http://localhost}"
    echo "APP_VERSION=${APP_VERSION:-1.0.14}"
    echo "APP_KEY=${APP_KEY:-base64:$(openssl rand -base64 32)}"
    echo "APP_LOCALE=${APP_LOCALE:-en}"
    echo "APP_FALLBACK_LOCALE=${APP_FALLBACK_LOCALE:-en}"
    echo "APP_MAINTENANCE_DRIVER=file"
    echo "PHP_CLI_SERVER_WORKERS=4"
    echo "BCRYPT_ROUNDS=12"
    echo "LOG_CHANNEL=stack"
    echo "LOG_STACK=single"
    echo "LOG_LEVEL=error"
    echo "DB_CONNECTION=mysql"
    echo "DB_HOST=${DB_HOST:-127.0.0.1}"
    echo "DB_PORT=${DB_PORT:-3306}"
    echo "DB_DATABASE=${DB_DATABASE:-cartxis}"
    echo "DB_USERNAME=${DB_USERNAME:-root}"
    echo "DB_PASSWORD=${DB_PASSWORD:-}"
    echo "SESSION_DRIVER=database"
    echo "SESSION_LIFETIME=120"
    echo "SESSION_ENCRYPT=false"
    echo "BROADCAST_CONNECTION=log"
    echo "FILESYSTEM_DISK=local"
    echo "QUEUE_CONNECTION=sync"
    echo "CACHE_STORE=database"
    echo "REDIS_CLIENT=phpredis"
    echo "REDIS_HOST=127.0.0.1"
    echo "REDIS_PASSWORD=null"
    echo "REDIS_PORT=6379"
    echo "MAIL_MAILER=log"
    echo "MAIL_FROM_ADDRESS=hello@example.com"
    echo "MAIL_FROM_NAME=${APP_NAME:-Cartxis}"
    echo "CARTXIS_THEME_DIRECTORY_URL=${CARTXIS_THEME_DIRECTORY_URL:-https://cartxis.com/api}"
    echo "CARTXIS_THEME_API_KEY=${CARTXIS_THEME_API_KEY:-}"
    echo "MYSQL_ATTR_SSL_CA=/etc/ssl/certs/ca-certificates.crt"
} > .env

# Generate app key if empty
if [ -z "$APP_KEY" ]; then
    APP_KEY="base64:$(openssl rand -base64 32)"
    sed -i "s|^APP_KEY=.*|APP_KEY=${APP_KEY}|" .env
fi

# Run migrations
php artisan migrate --force --no-interaction

# Seed database with admin credentials (only when setup has not been completed yet)
if [ -n "$CARTXIS_ADMIN_EMAIL" ] && php /var/www/html/scripts/needs_seed.php >/dev/null 2>&1; then
    php artisan db:seed --class='Cartxis\Core\Database\Seeders\DatabaseSeeder' --force --no-interaction
fi

# Storage link
php artisan storage:link 2>/dev/null || true

# Clear caches
php artisan optimize:clear

# Remove nginx package default vhost (listens on 80 returning 444) to avoid port confusion
rm -f /etc/nginx/conf.d/default.conf

# Bind nginx to Render's PORT (default 10000) so the port scan reliably detects it
PORT=${PORT:-10000}
sed -i "s|listen 80;|listen $PORT;|" /etc/nginx/sites-available/default

# Start php-fpm and nginx
exec supervisord -c /etc/supervisor/conf.d/supervisord.conf
