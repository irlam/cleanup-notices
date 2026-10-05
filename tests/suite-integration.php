<?php
declare(strict_types=1);

/**
 * Fail-closed authentication tests; intentionally no database access.
 * Safe to run on CI without live credentials or access to Plesk.
 */
$key = str_repeat('A', 48);
$root = dirname(__DIR__);
$invoke = static function (?string $secret, string $provided) use ($root): array {
    $code = 'putenv(' . var_export(
        'CONSTRUCTION_SUITE_API_KEY=' . ($secret ?? ''), true
    ) . '); $_SERVER["HTTP_X_CONSTRUCTION_SUITE_KEY"] = '
    . var_export($provided, true)
    . '; require ' . var_export($root . '/api/suite-summary.php', true) . ';';
    $process = proc_open([PHP_BINARY, '-r', $code], [
        0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']
    ], $pipes);
    if (!is_resource($process)) throw new RuntimeException('Could not run PHP');
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $errors = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    if (proc_close($process) !== 0) throw new RuntimeException($errors);
    $json = json_decode($output, true);
    if (!is_array($json)) throw new RuntimeException('Unexpected response: ' . $output);
    return $json;
};

if (($invoke(null, '')['error'] ?? '') !== 'suite_integration_not_configured') {
    throw new RuntimeException('Missing key must fail closed.');
}
if (($invoke($key, 'wrong')['error'] ?? '') !== 'unauthorized') {
    throw new RuntimeException('Incorrect key must be rejected.');
}

echo "PASS: Notices integration key checks fail closed before database access.\n";
