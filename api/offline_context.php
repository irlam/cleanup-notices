<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/offline_sync.php';
session_start();
if (empty($_SESSION['user'])) offline_reply(401, ['success'=>false, 'message'=>'Sign in to synchronise your saved notices.']);
if (empty($_SESSION['offline_csrf'])) $_SESSION['offline_csrf'] = bin2hex(random_bytes(32));
try {
    require_once __DIR__ . '/../includes/config.php';
    require_once __DIR__ . '/../includes/db.php';
    offline_schema($pdo);
    $recipients = $pdo->query('SELECT DISTINCT email FROM recipient_emails ORDER BY email')->fetchAll(PDO::FETCH_COLUMN);
    offline_reply(200, ['success'=>true, 'owner'=>(string)$_SESSION['user'], 'csrfToken'=>$_SESSION['offline_csrf'],
        'recipients'=>$recipients, 'refreshedAt'=>gmdate('c')]);
} catch (Throwable $error) {
    error_log('Offline context: '.$error->getMessage());
    offline_reply(503, ['success'=>false, 'message'=>'Offline setup is temporarily unavailable. Saved work remains on this device.']);
}
