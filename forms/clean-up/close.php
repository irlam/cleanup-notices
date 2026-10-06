<?php
// /forms/clean-up/close.php
declare(strict_types=1);
session_start();
if (!isset($_SESSION['user'])) { header('Location: /index.php'); exit; }

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';

$id = (int)($_GET['id'] ?? 0);
if ($id > 0) {
  $stmt = $pdo->prepare("UPDATE cleanup_notices SET status='closed', closed_at=NOW() WHERE id=?");
  $stmt->execute([$id]);
}
header("Location: list.php?closed=1");
exit;
