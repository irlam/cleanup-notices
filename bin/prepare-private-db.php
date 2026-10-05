<?php
declare(strict_types=1);

/**
 * ONE-TIME migration only, to run with Plesk PHP CLI before merging the
 * separate private DB configuration PR. No credentials are printed.
 *
 * This intentionally uses the currently deployed legacy includes/db.php
 * values. The file stays on disk across ordinary Plesk Git pull/deploy.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$includes = dirname(__DIR__) . '/includes';
$privatePath = $includes . '/db.local.php';

if (is_file($privatePath)) {
    echo "Private DB settings already exist; nothing overwritten.\n";
    exit(0);
}

if (!is_file($includes . '/db.php')) {
    fwrite(STDERR, "Existing database configuration unavailable.\n");
    exit(2);
}

// The previous PHP config declares these local variables and creates $pdo.
require $includes . '/db.php';

$values = [
    'host' => (string) ($host ?? ''),
    'port' => (string) ($port ?? '3306'),
    'name' => (string) ($db ?? ''),
    'user' => (string) ($user ?? ''),
    'pass' => (string) ($pass ?? ''),
];

foreach (['host', 'port', 'name', 'user', 'pass'] as $field) {
    if ($values[$field] === '') {
        fwrite(STDERR, "Incomplete legacy configuration; no changes made.\n");
        exit(2);
    }
}

$tmp = tempnam($includes, '.private-db-');
if ($tmp === false) {
    fwrite(STDERR, "Unable to create private config file.\n");
    exit(2);
}

try {
    chmod($tmp, 0600);
    $php = "<?php\n// Private configuration generated locally on Plesk.\nreturn "
        . var_export($values, true) . ";\n";
    if (file_put_contents($tmp, $php, LOCK_EX) === false) {
        throw new RuntimeException('Cannot write settings.');
    }

    // Abort rather than overwrite a concurrent operator-created config.
    if (is_file($privatePath)) {
        echo "Private settings already created by another process.\n";
        exit(0);
    }

    if (!rename($tmp, $privatePath)) {
        throw new RuntimeException('Cannot activate private settings.');
    }
    chmod($privatePath, 0600);
    echo "Private DB settings prepared. No credentials printed.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Private config migration failed; original file unchanged.\n");
    exit(2);
} finally {
    if (is_file($tmp)) @unlink($tmp);
}
