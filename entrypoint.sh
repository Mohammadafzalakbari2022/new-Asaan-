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

# Storage link
php artisan storage:link 2>/dev/null || true

# Clear caches
php artisan optimize:clear

# Remove nginx package default vhost (listens on 80 returning 444) to avoid port confusion
rm -f /etc/nginx/conf.d/default.conf

# Bind php-fpm to a unix socket (not TCP 9000, which Render's port scan misdetects as an HTTP port)
sed -i "s|^listen = 9000|listen = /run/php/php-fpm.sock|" /etc/php/8.3/fpm/pool.d/www.conf
sed -i "s|^listen = 127.0.0.1:9000|listen = /run/php/php-fpm.sock|" /etc/php/8.3/fpm/pool.d/www.conf
sed -i "/^listen.owner/a listen.owner = www-data\nlisten.group = www-data\nlisten.mode = 0660" /etc/php/8.3/fpm/pool.d/www.conf
sed -i "s|^pm.max_children = 5|pm.max_children = 10|" /etc/php/8.3/fpm/pool.d/www.conf

# Bind nginx to Render's PORT (default 10000) so the port scan reliably detects it.
# The stock Debian vhost uses `listen 80 default_server;` (not `listen 80;`), so a plain
# sed is fragile - write our own vhost deterministically. Also keep port 80 as a fallback.
PORT=${PORT:-10000}
cat > /etc/nginx/sites-available/default <<EOF
server {
    listen 80;
    listen [::]:80;
    listen ${PORT} default_server;
    listen [::]:${PORT} default_server;
    server_name _;
    root /var/www/html/public;
    index index.php;
    charset utf-8;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_pass unix:/run/php/php-fpm.sock;
        fastcgi_index index.php;
        fastcgi_read_timeout 120s;
    }
}
EOF

# Start php-fpm and nginx
exec supervisord -c /etc/supervisor/conf.d/supervisord.conf
