<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

require_once dirname(__DIR__) . '/includes/suite-integration.php';
notices_suite_require_key();

try {
    require_once dirname(__DIR__) . '/includes/db.php';

    $site = trim((string) ($_GET['site'] ?? ''));
    $where = $site !== '' ? 'WHERE site_name = :site' : '';

    $sql = "SELECT
        COUNT(*) AS total,
        COALESCE(SUM(CASE WHEN LOWER(COALESCE(status, 'open')) = 'open'
            THEN 1 ELSE 0 END), 0) AS open_count,
        COALESCE(SUM(CASE WHEN LOWER(COALESCE(status, 'open')) = 'closed'
            THEN 1 ELSE 0 END), 0) AS closed_count,
        COALESCE(SUM(CASE WHEN LOWER(COALESCE(status, 'open')) = 'open'
            AND deadline_at IS NOT NULL AND deadline_at < NOW()
            THEN 1 ELSE 0 END), 0) AS overdue,
        COALESCE(SUM(CASE WHEN COALESCE(LOWER(completed_ok), '') <> 'yes'
            THEN 1 ELSE 0 END), 0) AS pending_closeout,
        COALESCE(SUM(CASE WHEN created_at >= CURDATE()
            AND created_at < CURDATE() + INTERVAL 1 DAY
            THEN 1 ELSE 0 END), 0) AS created_today,
        MAX(created_at) AS last_updated
        FROM cleanup_notices {$where}";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($site !== '' ? [':site' => $site] : []);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $metrics = [];
    foreach (['total', 'open_count', 'closed_count', 'overdue', 'pending_closeout', 'created_today'] as $name) {
        $metrics[$name] = (int) ($row[$name] ?? 0);
    }

    echo json_encode([
        'ok' => true,
        'module' => 'notices',
        'scope' => ['site' => $site !== '' ? $site : null],
        'metrics' => $metrics,
        'last_updated' => $row['last_updated'] ?? null,
    ], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    error_log('Construction Suite notices summary failed: ' . $e->getMessage());
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'summary_unavailable']);
}
