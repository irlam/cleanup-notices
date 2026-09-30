<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/offline_sync.php';
session_start();
if (empty($_SESSION['user'])) offline_reply(401, ['success'=>false,'message'=>'Sign in to synchronise this close-out.']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') offline_reply(405, ['success'=>false,'message'=>'POST required.']);
if ((string)($_POST['offline_owner'] ?? '') !== (string)$_SESSION['user'] || empty($_SESSION['offline_csrf']) ||
    !hash_equals((string)$_SESSION['offline_csrf'], (string)($_POST['csrf_token'] ?? '')))
    offline_reply(403, ['success'=>false,'message'=>'Refresh your sign-in to synchronise.']);
$clientId = (string)($_POST['client_submission_id'] ?? '');
$id = (int)($_POST['notice_id'] ?? 0);
if ($id <= 0 || !preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/', $clientId))
    offline_reply(422, ['success'=>false,'message'=>'Invalid close-out.']);
$hash = hash('sha256', 'close:'.$id);
try {
    require_once __DIR__ . '/../includes/config.php';
    require_once __DIR__ . '/../includes/db.php';
    offline_schema($pdo);
    $receipt = offline_receipt($pdo, (string)$_SESSION['user'], $clientId);
    if ($receipt) {
        if (!hash_equals($receipt['payload_hash'], $hash)) offline_reply(409, ['success'=>false,'message'=>'Submission ID already used.']);
        offline_reply(200, offline_receipt_body($receipt));
    }
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('SELECT id FROM cleanup_notices WHERE id = ? FOR UPDATE');
    $stmt->execute([$id]);
    if (!$stmt->fetchColumn()) {
        $pdo->rollBack();
        offline_reply(422, ['success'=>false,'message'=>'This notice no longer exists.']);
    }
    $stmt = $pdo->prepare("INSERT INTO notice_submission_receipts (user_name, client_id, payload_hash, notice_id, mail_state) VALUES (?, ?, ?, ?, 'not_required')");
    $stmt->execute([$_SESSION['user'], $clientId, $hash, $id]);
    // Preserve the first close-out time, including when another device closed it.
    $stmt = $pdo->prepare("UPDATE cleanup_notices SET status = 'closed', closed_at = NOW() WHERE id = ? AND (status IS NULL OR status <> 'closed')");
    $stmt->execute([$id]);
    $pdo->commit();
    offline_reply(200, offline_receipt_body(offline_receipt($pdo, (string)$_SESSION['user'], $clientId)));
} catch (Throwable $error) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    $receipt = isset($pdo) ? offline_receipt($pdo, (string)$_SESSION['user'], $clientId) : null;
    if ($receipt && !hash_equals($receipt['payload_hash'], $hash)) offline_reply(409, ['success'=>false,'message'=>'Submission ID already used.']);
    if ($receipt && hash_equals($receipt['payload_hash'], $hash)) offline_reply(200, offline_receipt_body($receipt));
    error_log('Offline close-out: '.$error->getMessage());
    offline_reply(503, ['success'=>false,'message'=>'Close-out not confirmed yet. It remains saved on this device.']);
}
