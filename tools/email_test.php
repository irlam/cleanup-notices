<?php
declare(strict_types=1);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

echo "<pre>";

$base = realpath(__DIR__ . '/..');
echo "Base: {$base}\n";

// Check files exist
$paths = [
  $base . '/lib/PHPMailer/PHPMailer.php',
  $base . '/lib/PHPMailer/SMTP.php',
  $base . '/lib/PHPMailer/Exception.php',
  $base . '/includes/mail.php',
];
foreach ($paths as $p) {
  echo (is_readable($p) ? "OK   " : "MISS ") . $p . "\n";
}

require_once $base . '/includes/mail.php';

$r = send_mail('test-xls11m6xc@srv1.mail-tester.com', 'SMTP Test via PHPMailer', '<p>Hello from Defect Tracker.</p>');
var_dump($r);
