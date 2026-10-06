<?php
declare(strict_types=1);

// Test the actual authenticated read-only API against a disposable DB.
// This script is for CI only; do not run against production.
$key = bin2hex(random_bytes(32));
putenv('CONSTRUCTION_SUITE_API_KEY=' . $key);
if (strlen($key) < 32 || !getenv('NOTICES_DB_NAME')) {
    fwrite(STDERR, "Disposable test database and key required.\n");
    exit(2);
}

$root = dirname(__DIR__);
$call = static function (string $file, string $site) use ($root, $key): array {
    $script = '\$_SERVER["HTTP_X_CONSTRUCTION_SUITE_KEY"] = ' .
        var_export($key, true) .
        '; \$_GET["site"] = ' . var_export($site, true) .
        '; require ' . var_export($root . '/api/' . $file, true) . ';';
    // Interpret the code as PHP without using shell string interpolation.
    $script = str_replace('\\$', '$', $script);
    $process = proc_open([PHP_BINARY, '-r', $script], [
        0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']
    ], $pipes);
    if (!is_resource($process)) throw new RuntimeException('PHP CLI unavailable.');
    fclose($pipes[0]);
    $body = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $exit = proc_close($process);
    $decoded = json_decode((string) $body, true);
    if ($exit !== 0 || !is_array($decoded) || ($decoded['ok'] ?? false) !== true) {
        throw new RuntimeException('API contract failed: ' . $error);
    }
    return $decoded;
};

$assert = static function (bool $condition, string $description): void {
    if (!$condition) throw new RuntimeException('FAIL: ' . $description);
};

$all = $call('suite-summary.php', '');
$a = $call('suite-summary.php', 'Test Site A');
$b = $call('suite-summary.php', 'Test Site B');
$unknown = $call('suite-summary.php', 'Does Not Exist');
$refs = $call('suite-references.php', '');
$assert(($all['metrics']['total'] ?? null) === 2, 'All sites should total two');
$assert(($all['metrics']['open_count'] ?? null) === 1, 'Open notices count');
$assert(($all['metrics']['closed_count'] ?? null) === 1, 'Closed notices count');
$assert(($all['metrics']['overdue'] ?? null) === 1, 'Overdue count');
$assert(($all['metrics']['pending_closeout'] ?? null) === 1, 'Pending close-out count');
$assert(($a['metrics']['total'] ?? null) === 1, 'Site A must be isolated');
$assert(($a['metrics']['open_count'] ?? null) === 1, 'Site A must be open');
$assert(($b['metrics']['total'] ?? null) === 1, 'Site B must be isolated');
$assert(($b['metrics']['closed_count'] ?? null) === 1, 'Site B must be closed');
$assert(($unknown['metrics']['total'] ?? null) === 0, 'Unknown site must return no data');
$items = array_column((array) ($refs['items'] ?? []), 'value');
sort($items);
$assert($items === ['Test Site A', 'Test Site B'], 'Exact site references');

echo "PASS: authenticated Notices metrics, site isolation and discovered site references.\n";
