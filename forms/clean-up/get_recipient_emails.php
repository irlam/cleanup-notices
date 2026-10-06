<?php
// /forms/clean-up/get_recipient_emails.php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json');

try {
  $stmt = $pdo->query("SELECT DISTINCT email FROM recipient_emails ORDER BY email ASC");
  $emails = $stmt->fetchAll(PDO::FETCH_COLUMN);
  echo json_encode($emails);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([]);
}
