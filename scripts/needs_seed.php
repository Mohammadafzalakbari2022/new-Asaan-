<?php

// Pre-boot check used by entrypoint.sh. Exits 0 when the database still needs
// seeding (or the DB is unavailable), and 1 when setup has already completed.
// Connection settings come from the process environment (Render injects them),
// matching the defaults entrypoint.sh uses.

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$database = getenv('DB_DATABASE') ?: '';
$user = getenv('DB_USERNAME') ?: '';
$pass = getenv('DB_PASSWORD') ?: '';

if ($database === '' || $user === '') {
    exit(0);
}

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $database),
        $user,
        $pass,
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