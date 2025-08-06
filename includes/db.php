<?php
// includes/db.php — PDO connection with sensible defaults (no output)

$host = '10.35.233.124';
$port = '3306';
$db   = 'k87747_docs';
$user = 'k87747_docs';
$pass = '6fv_s3R58'; // <— update

$dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";

$options = [
  PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
  $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
  // Log error to server logs; do not echo
  error_log('DB connection failed: ' . $e->getMessage());
  http_response_code(500);
  exit('Database connection error.');
}
