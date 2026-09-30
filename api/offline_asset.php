<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/offline_sync.php';
session_start();
if (empty($_SESSION['user'])) offline_reply(401, ['success'=>false,'message'=>'Sign in required.']);
header('Cache-Control: no-store, private');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
$id = (int)($_GET['id'] ?? 0);
$kind = (string)($_GET['kind'] ?? 'pdf');
$stmt = $pdo->prepare('SELECT id, pdf_path, signature_path FROM cleanup_notices WHERE id = ?');
$stmt->execute([$id]);
$notice = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$notice) offline_reply(404, ['success'=>false,'message'=>'Notice not found.']);
if ($kind === 'pdf') {
    $path = $notice['pdf_path'] ?? '';
} elseif ($kind === 'signature') {
    $path = $notice['signature_path'] ?? '';
} elseif ($kind === 'photo') {
    $stmt = $pdo->prepare('SELECT path FROM cleanup_photos WHERE id = ? AND notice_id = ?');
    $stmt->execute([(int)($_GET['photo'] ?? 0), $id]);
    $path = $stmt->fetchColumn() ?: '';
} else offline_reply(400, ['success'=>false,'message'=>'Invalid asset type.']);
$real = $path ? realpath((string)$path) : false;
$root = realpath(__DIR__ . '/../uploads/cleanup/'.$id);
if (!$real || !$root || !is_file($real) || strncmp($real, $root.DIRECTORY_SEPARATOR, strlen($root)+1) !== 0) {
    if ($kind === 'pdf') {
        $_GET['id'] = $id;
        require __DIR__ . '/../forms/clean-up/pdf.php';
        exit;
    }
    offline_reply(404, ['success'=>false,'message'=>'Asset unavailable.']);
}
header('Content-Type: '.($kind === 'pdf' ? 'application/pdf' : 'image/jpeg'));
header('Content-Disposition: inline; filename="'.($kind === 'pdf' ? 'notice-'.$id.'.pdf' : 'notice-'.$id.'.jpg').'"');
header('X-Content-Type-Options: nosniff');
readfile($real);
