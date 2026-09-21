FROM php:8.3-fpm

# System deps + PHP extensions + nginx + supervisor + Node.js + openssl + ca-certificates
RUN apt-get update && apt-get install -y --no-install-recommends \
    nginx supervisor curl git unzip openssl libpng-dev libjpeg-dev libfreetype6-dev \
    libzip-dev zlib1g-dev libonig-dev libicu-dev libxml2-dev ca-certificates libpq-dev \
    && curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && docker-php-ext-install pdo_mysql pgsql pdo_pgsql mbstring xml bcmath zip gd intl exif opcache \
    && docker-php-ext-enable opcache \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY . .

# PHP dependencies (cache mount + retries keep installs reliable on slow/flaky networks)
ENV COMPOSER_PROCESS_TIMEOUT=1800
RUN --mount=type=cache,target=/root/.cache/composer \
    for i in 1 2 3 4 5; do \
        composer install --no-dev --optimize-autoloader --no-interaction --ignore-platform-reqs --no-scripts \
        && break || sleep 10; \
    done; \
    test -f vendor/autoload.php

# Create minimal .env for wayfinder build (entrypoint overwrites it at runtime)
RUN cat > .env << 'EOF'
APP_NAME=Cartxis
APP_ENV=local
APP_DEBUG=false
APP_URL=http://localhost
APP_KEY=base64:placeholder
DB_CONNECTION=null
DB_HOST=localhost
DB_PORT=4000
DB_DATABASE=sys
DB_USERNAME=root
DB_PASSWORD=
SESSION_DRIVER=file
QUEUE_CONNECTION=sync
CACHE_STORE=file
MAIL_MAILER=log
MYSQL_ATTR_SSL_CA=/etc/ssl/certs/ca-certificates.crt
EOF

# Generate valid APP_KEY for wayfinder during build
RUN rm -f bootstrap/cache/*.php && mkdir -p bootstrap/cache storage/framework/cache storage/framework/sessions storage/framework/views storage/app/public && php artisan key:generate

# Node dependencies (cache mount + retries) + build frontend (PHP available for @laravel/vite-plugin-wayfinder)
RUN --mount=type=cache,target=/root/.npm \
    for i in 1 2 3 4 5; do \
        npm ci --no-audit --no-fund \
        && break || sleep 10; \
    done; \
    npm run build

# Permissions + storage dirs
RUN mkdir -p storage/framework/{cache,sessions,views} storage/app/public \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 storage bootstrap/cache

# Nginx + Supervisor config
COPY nginx.conf /etc/nginx/sites-available/default
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Entrypoint
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 80 7860
ENTRYPOINT ["/entrypoint.sh"]
