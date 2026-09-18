FROM php:8.3-fpm

# System deps + PHP extensions + nginx + supervisor + Node.js + openssl + ca-certificates
RUN apt-get update && apt-get install -y --no-install-recommends \
    nginx supervisor curl git unzip openssl libpng-dev libjpeg-dev libfreetype6-dev \
    libzip-dev zlib1g-dev libonig-dev libicu-dev ca-certificates \
    && curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && docker-php-ext-install pdo_mysql mbstring xml bcmath zip gd intl exif opcache \
    && docker-php-ext-enable opcache \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY . .

# PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction --ignore-platform-reqs --no-scripts

# Create .env for wayfinder build (entrypoint overwrites it at runtime)
COPY .env.example .env

# Node dependencies + build frontend (PHP available for @laravel/vite-plugin-wayfinder)
RUN npm ci && npm run build

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
