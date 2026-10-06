<?php
declare(strict_types=1);

// Non-destructive check only; cannot substitute for staging restore.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$dir = dirname(__DIR__, 2) . '/.site-backups/notices';
$files = glob($dir . '/notices-*.sql.gz') ?: [];
rsort($files, SORT_STRING);
$latest = $files[0] ?? '';
if ($latest === '' || !is_file($latest . '.sha256')) {
    fwrite(STDERR, "No verified database backup found.\n");
    exit(2);
}
$expected = explode(' ', trim((string) file_get_contents($latest . '.sha256')))[0] ?? '';
$actual = hash_file('sha256', $latest);
if ($expected === '' || !hash_equals($expected, $actual)) {
    fwrite(STDERR, "Backup checksum mismatch.\n");
    exit(2);
}
$gz = gzopen($latest, 'rb');
if (!$gz) {
    fwrite(STDERR, "Unable to inspect compressed backup.\n");
    exit(2);
}
$bytes = 0;
$hasSchema = false;
$tail = '';
try {
    while (!gzeof($gz)) {
        $part = gzread($gz, 65536);
        if ($part === false) throw new RuntimeException('Corrupt compressed backup.');
        $bytes += strlen($part);
        $block = $tail . $part;
        if (stripos($block, 'CREATE TABLE') !== false) $hasSchema = true;
        $tail = substr($block, -16);
    }
} catch (Throwable $e) {
    fwrite(STDERR, "Backup integrity failed.\n");
    exit(2);
} finally {
    gzclose($gz);
}
if ($bytes < 100 || !$hasSchema) {
    fwrite(STDERR, "Backup is missing expected database schema.\n");
    exit(2);
}
echo 'Backup integrity verified: ' . basename($latest) . "\n";
echo "This does not verify restoring data or attachments.\n";
