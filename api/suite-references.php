<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

require_once dirname(__DIR__) . '/includes/suite-integration.php';
notices_suite_require_key();

try {
    require_once dirname(__DIR__) . '/includes/db.php';
    $stmt = $pdo->query(
        "SELECT DISTINCT TRIM(site_name) AS site
         FROM cleanup_notices
         WHERE site_name IS NOT NULL AND TRIM(site_name) <> ''
         ORDER BY site ASC"
    );

    $items = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $site = (string) ($row['site'] ?? '');
        if ($site === '') continue;
        $items[] = ['value' => $site, 'label' => $site];
    }
    echo json_encode([
        'ok' => true,
        'module' => 'notices',
        'reference_type' => 'site',
        'items' => $items,
    ], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    error_log('Construction Suite notices references failed: ' . $e->getMessage());
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'references_unavailable']);
}
