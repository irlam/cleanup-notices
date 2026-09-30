<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/offline_sync.php';
session_start();
if (empty($_SESSION['user'])) offline_reply(401, ['success'=>false,'message'=>'Sign in required.']);
try {
    require_once __DIR__ . '/../includes/config.php';
    require_once __DIR__ . '/../includes/db.php';
    $receipt = offline_receipt($pdo, (string)$_SESSION['user'], (string)($_GET['client_id'] ?? ''));
    if (!$receipt) offline_reply(404, ['success'=>false,'message'=>'Receipt not found.']);
    offline_reply(200, ['owner'=>(string)$_SESSION['user']] + offline_receipt_body($receipt));
} catch (Throwable $error) {
    error_log('Offline receipt: '.$error->getMessage());
    offline_reply(503, ['success'=>false,'message'=>'Receipt temporarily unavailable.']);
}
