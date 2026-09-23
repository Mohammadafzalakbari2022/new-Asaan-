#!/bin/bash
set -e
cd /var/www/html
MYSQL_ATTR_SSL_CA="${MYSQL_ATTR_SSL_CA-/etc/ssl/certs/ca-certificates.crt}"

# Ensure .env exists
[ -f .env ] || cp .env.example .env

# Write .env from OS environment variables (provided by Render)
{
    echo "APP_NAME=\"${APP_NAME:-Akbari Development Group}\""
    echo "APP_ENV=production"
    echo "APP_DEBUG=false"
    echo "APP_URL=${APP_URL:-http://localhost}"
    echo "ASSET_URL=${ASSET_URL:-}"
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
    echo "DB_CONNECTION=${DB_CONNECTION:-mysql}"
    echo "DB_HOST=${DB_HOST:-127.0.0.1}"
    echo "DB_PORT=${DB_PORT:-3306}"
    echo "DB_DATABASE=${DB_DATABASE:-cartxis}"
    echo "DB_USERNAME=${DB_USERNAME:-root}"
    echo "DB_PASSWORD=${DB_PASSWORD:-}"
    echo "SESSION_DRIVER=${SESSION_DRIVER:-database}"
    echo "SESSION_LIFETIME=120"
    echo "SESSION_ENCRYPT=false"
    echo "BROADCAST_CONNECTION=log"
    echo "FILESYSTEM_DISK=local"
    echo "QUEUE_CONNECTION=sync"
    echo "CACHE_STORE=${CACHE_STORE:-database}"
    echo "REDIS_CLIENT=phpredis"
    echo "REDIS_HOST=127.0.0.1"
    echo "REDIS_PASSWORD=null"
    echo "REDIS_PORT=6379"
    echo "MAIL_MAILER=log"
    echo "MAIL_FROM_ADDRESS=hello@example.com"
    echo "MAIL_FROM_NAME=\"${APP_NAME:-Cartxis}\""
    echo "CARTXIS_THEME_DIRECTORY_URL=${CARTXIS_THEME_DIRECTORY_URL:-https://cartxis.com/api}"
    echo "CARTXIS_THEME_API_KEY=${CARTXIS_THEME_API_KEY:-}"
    echo "MYSQL_ATTR_SSL_CA=${MYSQL_ATTR_SSL_CA}"
} > .env

# Generate app key if empty
if [ -z "$APP_KEY" ]; then
    APP_KEY="base64:$(openssl rand -base64 32)"
    sed -i "s|^APP_KEY=.*|APP_KEY=${APP_KEY}|" .env
fi

# Storage link
php artisan storage:link 2>/dev/null || true

# Seed fallback brand assets (Akbari Development Group + Asaan logos) from
# public/logos into the storage mirror. The Docker image excludes storage/* at
# build time, but branding settings and fallbacks resolve under /storage/.
mkdir -p storage/app/public/logos
cp -f public/logos/* storage/app/public/logos/ 2>/dev/null || true

# Clear compiled/view/config/route caches. Deliberately avoids `cache:clear`:
# that one goes through the database-backed cache store and does `delete from
# "cache"`, which fails on a fresh Render PostgreSQL before provisioning has run
# (no migrations table yet) and would abort the entrypoint via `set -e`.
php artisan clear-compiled 2>/dev/null || true
php artisan config:clear
php artisan route:clear
php artisan event:clear
php artisan view:clear

# Warm up the TiDB serverless cluster IN THE BACKGROUND so nginx and php-fpm boot
# instantly and Render's port scan + health check pass even while the cluster is
# still waking from sleep (a cold TiDB can take 60-120s; Render gives the container
# only ~90s to answer). Real page requests may see brief flashes of DB errors while
# it wakes, then everything is normal.
if [ "${DB_CONNECTION:-mysql}" = "mysql" ] && [ -n "$DB_HOST" ]; then
    (
        attempts=0
        WARM_PHP=$(cat <<'PHPEOF'
$host = getenv('DB_HOST');
$port = getenv('DB_PORT') !== false && getenv('DB_PORT') !== '' ? getenv('DB_PORT') : '4000';
$db = getenv('DB_DATABASE');
$u = getenv('DB_USERNAME');
$p = getenv('DB_PASSWORD');
$opts = [PDO::ATTR_TIMEOUT => 10];
$ca = getenv('MYSQL_ATTR_SSL_CA');
if ($ca) { $opts[PDO::MYSQL_ATTR_SSL_CA] = $ca; }
$pdo = new PDO("mysql:host={$host};port={$port};dbname={$db}", $u, $p, $opts);
$pdo->query('SELECT 1');
echo "DB-WARM OK\n";
PHPEOF
        )
        while [ $attempts -lt 6 ]; do
            attempts=$((attempts + 1))
            if timeout 25 php -r "$WARM_PHP" 2>/dev/null; then
                echo "[entrypoint] Database is warm (attempt $attempts)"
                break
            else
                echo "[entrypoint] Database not ready yet (attempt $attempts), retrying..."
                sleep 10
            fi
        done
    ) &
fi

# One-time provisioning for PostgreSQL databases (pgsql driver). Runs in the
# background so nginx/php-fpm boot instantly and Render's port scan passes even on
# a genuinely fresh render.PostgreSQL. It only provisions when the database has no
# migrations table yet (i.e. completely new), so a plain redeploy never re-runs it.
if [ "${DB_CONNECTION:-mysql}" = "pgsql" ] && [ "${RUN_MIGRATE:-0}" = "1" ]; then
    (
        CHECK_PHP=$(cat <<'PHPEOF'
$host = getenv('DB_HOST');
$port = getenv('DB_PORT') !== false && getenv('DB_PORT') !== '' ? getenv('DB_PORT') : '5432';
$db = getenv('DB_DATABASE');
$u = getenv('DB_USERNAME');
$p = getenv('DB_PASSWORD');
$ssl = getenv('DB_SSLMODE') ?: 'require';
$dsn = "pgsql:host={$host};port={$port};dbname={$db};sslmode={$ssl}";
$o = [PDO::ATTR_TIMEOUT => 30];
try {
    $pdo = new PDO($dsn, $u, $p, $o);
    $n = (int)$pdo->query("select count(*) from pg_catalog.pg_class c join pg_catalog.pg_namespace n on n.oid = c.relnamespace where c.relname = 'migrations' and n.nspname = current_schema()")->fetchColumn();
} catch (PDOException $e) {
    echo "CHECK-FAIL: " . $e->getMessage() . "\n";
    $n = -1;
}
echo "CHECK-RESULT:$n\n";
PHPEOF
        )
        RES=$(timeout 30 php -r "$CHECK_PHP" 2>&1)
        echo "[entrypoint] PostgreSQL setup check: $RES"
        if printf '%s' "$RES" | grep -q 'CHECK-RESULT:0'; then
            echo "[entrypoint] Empty PostgreSQL detected - running migrate + seed once..."
            if timeout 900 php artisan migrate --force --seed 2>&1; then
                echo "[entrypoint] Database provisioning complete"
            else
                echo "[entrypoint] Database provisioning failed - retrying once after 30s..."
                sleep 30
                php artisan migrate --force --seed 2>&1
            fi
        else
            echo "[entrypoint] Database already provisioned (or check errored) - skipping setup"
        fi
    ) &
fi

# Keep the free-tier Render PostgreSQL awake: it pauses after ~15 min without a
# connection, and a paused DB makes every page 500 while the container waits for it
# to come back. Ping it every 120s so it never sleeps while this container runs.
if [ "${DB_CONNECTION:-mysql}" = "pgsql" ]; then
    (
        KEEPALIVE_PHP=$(cat <<'PHPEOF'
$host = getenv('DB_HOST');
$port = getenv('DB_PORT') !== false && getenv('DB_PORT') !== '' ? getenv('DB_PORT') : '5432';
$db = getenv('DB_DATABASE');
$u = getenv('DB_USERNAME');
$p = getenv('DB_PASSWORD');
$ssl = getenv('DB_SSLMODE') ?: 'require';
$dsn = "pgsql:host={$host};port={$port};dbname={$db};sslmode={$ssl}";
try { $pdo = new PDO($dsn, $u, $p, [PDO::ATTR_TIMEOUT => 20]); $pdo->query('SELECT 1'); }
catch (Exception $e) { fwrite(STDERR, "keepalive: " . $e->getMessage() . "\n"); }
PHPEOF
        )
        while true; do
            sleep 120
            timeout 30 php -r "$KEEPALIVE_PHP" >/dev/null 2>&1 || echo "[entrypoint] DB keepalive ping failed"
        done
    ) &
fi

# Remove nginx package default vhost (listens on 80 returning 444) to avoid port confusion
rm -f /etc/nginx/conf.d/default.conf

# Bind php-fpm to a unix socket (not TCP 9000, which Render's port scan misdetects as an HTTP port)
if [ -f /usr/local/etc/php-fpm.d/www.conf ]; then
    FPM_POOL=/usr/local/etc/php-fpm.d/www.conf
else
    FPM_POOL=/etc/php/8.3/fpm/pool.d/www.conf
fi
mkdir -p /run/php
sed -i "s|^listen = 9000|listen = /run/php/php-fpm.sock|" "$FPM_POOL"
sed -i "s|^listen = 127.0.0.1:9000|listen = /run/php/php-fpm.sock|" "$FPM_POOL"
sed -i "s|^listen = /run/php/php-fpm.sock|&\nlisten.owner = www-data\nlisten.group = www-data\nlisten.mode = 0660|" "$FPM_POOL"
sed -i "s|^pm.max_children = 5|pm.max_children = ${FPM_MAX_CHILDREN:-10}|" "$FPM_POOL"
{
    echo ""
    echo "listen = /run/php/php-fpm.sock"
    echo "listen.owner = www-data"
    echo "listen.group = www-data"
    echo "listen.mode = 0660"
    echo "pm.max_children = ${FPM_MAX_CHILDREN:-10}"
    if [ "${FPM_MAX_CHILDREN:-10}" -lt 3 ]; then
        echo "pm.max_spare_servers = ${FPM_MAX_CHILDREN}"
    fi
} >> "$FPM_POOL"

# Bind nginx to the platform-provided PORT (default 80) so the image is portable across
# hosts: Koyeb/Railway default 80, Hugging Face Spaces requires 7860, etc.
# A plain sed over the stock Debian vhost is fragile (`listen 80 default_server;`, not
# `listen 80;`), so write our own vhost and substitute the port via a placeholder
# (an unquoted heredoc would let bash eat nginx's own $uri/$document_root variables).
cat > /etc/nginx/sites-available/default <<'EOF'
server {
    listen __PORT__ default_server;
    server_name _;
    root /var/www/html/public;
    index index.php;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param HTTP_HOST $http_host;
        fastcgi_pass unix:/run/php/php-fpm.sock;
        fastcgi_index index.php;
        fastcgi_read_timeout 120s;
    }
}
EOF

sed -i "s|__PORT__|${PORT:-80}|g" /etc/nginx/sites-available/default

# Start php-fpm and nginx
exec supervisord -c /etc/supervisor/conf.d/supervisord.conf
