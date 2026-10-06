<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/offline_sync.php';
session_start();
if (empty($_SESSION['user'])) offline_reply(401, ['success'=>false,'message'=>'Sign in required.']);
try {
    require_once __DIR__ . '/../includes/config.php';
    require_once __DIR__ . '/../includes/db.php';
    // Match the notice list's existing visibility for signed-in users.
    $notices = $pdo->query('SELECT id, site_name, location, issued_at, issued_to, issued_by, reason, description,
        urgency, deadline_at, completed_ok, mcgoff_clear, status, created_at FROM cleanup_notices ORDER BY id DESC LIMIT 50')->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $pdo->prepare('SELECT id FROM cleanup_photos WHERE notice_id = ? ORDER BY id');
    foreach ($notices as &$notice) {
        $stmt->execute([(int)$notice['id']]);
        $notice['photoIds'] = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }
    unset($notice);
    offline_reply(200, ['success'=>true,'owner'=>(string)$_SESSION['user'],'notices'=>$notices,'refreshedAt'=>gmdate('c')]);
} catch (Throwable $error) {
    error_log('Offline notices: '.$error->getMessage());
    offline_reply(503, ['success'=>false,'message'=>'Unable to download notices.']);
}
