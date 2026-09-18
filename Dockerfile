# ---- Stage 1: Composer dependencies ----
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts --ignore-platform-reqs
COPY . .
RUN composer install --no-dev --optimize-autoloader --no-interaction --ignore-platform-reqs

# ---- Stage 2: Frontend build (Node.js) ----
FROM node:22-slim AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build

# ---- Stage 3: Runtime (PHP + Nginx + Supervisor) ----
FROM php:8.3-fpm

# System deps + PHP extensions + nginx + supervisor + openssl + ca-certificates
RUN apt-get update && apt-get install -y --no-install-recommends \
    nginx supervisor curl git unzip openssl libpng-dev libjpeg-dev libfreetype6-dev \
    libzip-dev zlib1g-dev libonig-dev libicu-dev ca-certificates \
    && docker-php-ext-install pdo_mysql mbstring xml bcmath zip gd intl exif opcache \
    && docker-php-ext-enable opcache \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

# Copy vendor + source from composer stage
COPY --from=vendor /app/vendor ./vendor
COPY . .

# Copy built frontend assets from frontend stage
COPY --from=frontend /app/public/build ./public/build

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

EXPOSE 80
ENTRYPOINT ["/entrypoint.sh"]
