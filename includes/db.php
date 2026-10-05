<?php
declare(strict_types=1);

/**
 * Private PDO settings. NEVER commit a database password.
 *
 * Preferred: create includes/db.local.php (ignored by Git), returning
 * ['host' => '...', 'port' => '3306', 'name' => '...', 'user' => '...', 'pass' => '...'].
 * Alternatively supply NOTICES_DB_* server environment variables.
 */
$privateFile = __DIR__ . '/db.local.php';
$private = is_file($privateFile) ? require $privateFile : [];
if (!is_array($private)) {
    $private = [];
}

$host = trim((string) ($private['host'] ?? (getenv('NOTICES_DB_HOST') ?: '')));
$port = trim((string) ($private['port'] ?? (getenv('NOTICES_DB_PORT') ?: '3306')));
$db   = trim((string) ($private['name'] ?? (getenv('NOTICES_DB_NAME') ?: '')));
$user = trim((string) ($private['user'] ?? (getenv('NOTICES_DB_USER') ?: '')));
$pass = (string) ($private['pass'] ?? (getenv('NOTICES_DB_PASS') ?: ''));

if ($host === '' || $db === '' || $user === '' || $pass === '') {
    error_log('Site Notices database configuration is missing.');
    http_response_code(503);
    exit('Database configuration is incomplete.');
}

$dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    error_log('Site Notices database connection failed: ' . $e->getMessage());
    http_response_code(503);
    exit('Database connection unavailable.');
}
