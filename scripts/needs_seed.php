<?php

// Pre-boot check used by entrypoint.sh. Exits 0 when the database still needs
// seeding (or the DB is unavailable), and 1 when setup has already completed.
// Guessing that the caller wants a loud, obvious failure if seeding is needed
// but cannot run, so DB errors return 0 (attempt seed) rather than silently
// skipping market-critical data.

$env = parse_ini_file('/var/www/html/.env');
if ($env === false || ! isset($env['DB_HOST'], $env['DB_PORT'], $env['DB_DATABASE'], $env['DB_USERNAME'])) {
    exit(0);
}

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $env['DB_HOST'], $env['DB_PORT'], $env['DB_DATABASE']),
        $env['DB_USERNAME'],
        $env['DB_PASSWORD'] ?? '',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::MYSQL_ATTR_SSL_CA => '/etc/ssl/certs/ca-certificates.crt',
            PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
        ]
    );

    $count = (int) $pdo->query("SELECT COUNT(*) FROM settings WHERE `key` = 'setup_completed' AND `value` = '1'")->fetchColumn();
} catch (Throwable $e) {
    exit(0);
}

exit($count > 0 ? 1 : 0);