<?php
declare(strict_types=1);

// CLI only: write consistent MariaDB dumps OUTSIDE httpdocs and Git.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$root = dirname(__DIR__);
$configFile = $root . '/includes/db.local.php';
$config = is_file($configFile) ? require $configFile : [];
if (!is_array($config)) $config = [];
$required = [
    'host' => (string)($config['host'] ?? (getenv('NOTICES_DB_HOST') ?: '')),
    'port' => (string)($config['port'] ?? (getenv('NOTICES_DB_PORT') ?: '3306')),
    'name' => (string)($config['name'] ?? (getenv('NOTICES_DB_NAME') ?: '')),
    'user' => (string)($config['user'] ?? (getenv('NOTICES_DB_USER') ?: '')),
    'pass' => (string)($config['pass'] ?? (getenv('NOTICES_DB_PASS') ?: '')),
];
foreach ($required as $key => $value) {
    if ($value === '') {
        fwrite(STDERR, "Missing private DB configuration; backup not started.\n");
        exit(2);
    }
}
if (!preg_match('/^[A-Za-z0-9_]+$/', $required['name'])
    || !ctype_digit($required['port']) || (int)$required['port'] > 65535) {
    fwrite(STDERR, "Unsupported DB name or port.\n");
    exit(2);
}
$bin = '';
foreach (['/usr/bin/mariadb-dump', '/usr/bin/mysqldump', '/bin/mysqldump'] as $candidate) {
    if (is_executable($candidate)) { $bin = $candidate; break; }
}
if ($bin === '' || !function_exists('proc_open')) {
    fwrite(STDERR, "MariaDB dump utility unavailable; configure Plesk Backup Manager.\n");
    exit(2);
}

$dest = dirname($root) . '/.site-backups/notices';
if (!is_dir($dest) && !mkdir($dest, 0700, true) && !is_dir($dest)) {
    fwrite(STDERR, "Cannot create private backup directory.\n");
    exit(2);
}
chmod($dest, 0700);
$opt = tempnam($dest, '.db-option-');
$sql = tempnam($dest, '.db-dump-');
if ($opt === false || $sql === false) {
    fwrite(STDERR, "Cannot stage backup.\n");
    exit(2);
}
$gzipTemp = $dest . '/.backup-' . bin2hex(random_bytes(8)) . '.gz.tmp';
$completed = false;
try {
    chmod($opt, 0600);
    chmod($sql, 0600);
    $escape = static fn(string $value): string => str_replace(
        ["\\", '"', "\r", "\n"],
        ["\\\\", '\\"', '', ''],
        $value
    );
    $defaults = "[client]\n"
        . 'host="' . $escape($required['host']) . "\"\n"
        . 'port=' . $required['port'] . "\n"
        . 'user="' . $escape($required['user']) . "\"\n"
        . 'password="' . $escape($required['pass']) . "\"\n";
    if (file_put_contents($opt, $defaults, LOCK_EX) === false) {
        throw new RuntimeException('Cannot create private client options.');
    }
    $proc = proc_open([
        $bin,
        '--defaults-extra-file=' . $opt,
        '--single-transaction',
        '--quick',
        '--routines',
        '--triggers',
        '--hex-blob',
        $required['name'],
        '--result-file=' . $sql,
    ], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($proc)) throw new RuntimeException('Backup process unavailable.');
    fclose($pipes[0]);
    stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    if (proc_close($proc) !== 0 || filesize($sql) < 100) {
        throw new RuntimeException('Database export failed; inspect Plesk logs. ' .
            substr(preg_replace('/[^a-zA-Z0-9 .:_-]/', '', $stderr), 0, 150));
    }
    $in = fopen($sql, 'rb');
    $out = gzopen($gzipTemp, 'wb9');
    if (!$in || !$out) throw new RuntimeException('Compression unavailable.');
    while (!feof($in)) {
        $chunk = fread($in, 65536);
        if ($chunk === false || gzwrite($out, $chunk) === false) {
            throw new RuntimeException('Compression interrupted.');
        }
    }
    fclose($in);
    gzclose($out);
    chmod($gzipTemp, 0600);
    $name = 'notices-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.sql.gz';
    $final = $dest . '/' . $name;
    if (!rename($gzipTemp, $final)) throw new RuntimeException('Cannot finalize dump.');
    file_put_contents($final . '.sha256', hash_file('sha256', $final) . "  " . $name . "\n", LOCK_EX);
    chmod($final . '.sha256', 0600);
    // Keep 30 latest successful dumps; never touch files without our prefix.
    $all = glob($dest . '/notices-*.sql.gz') ?: [];
    rsort($all, SORT_STRING);
    foreach (array_slice($all, 30) as $older) {
        @unlink($older);
        @unlink($older . '.sha256');
    }
    echo 'Backup complete: ' . $name . ' (private directory).\n';
    $completed = true;
} catch (Throwable $e) {
    fwrite(STDERR, 'Backup failed: ' . $e->getMessage() . "\n");
} finally {
    @unlink($opt);
    @unlink($sql);
    @unlink($gzipTemp);
}
exit($completed ? 0 : 2);
