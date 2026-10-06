<?php
// forms/clean-up/submit.php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/offline_sync.php';
$syncRequest = ($_SERVER['HTTP_X_OFFLINE_SUBMISSION'] ?? '') === '1';
session_start();
if (!isset($_SESSION['user'])) {
  if ($syncRequest) offline_reply(401, ['success'=>false,'message'=>'Sign in as the notice author to synchronise.']);
  header('Location: /index.php'); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') offline_reply(405, ['success'=>false,'message'=>'POST required.']);
if ($syncRequest && (string)($_POST['offline_owner'] ?? '') !== (string)$_SESSION['user'])
  offline_reply(403, ['success'=>false,'message'=>'Sign in as the user who created this notice.']);
if (empty($_SESSION['offline_csrf']) || !hash_equals((string)$_SESSION['offline_csrf'], (string)($_POST['csrf_token'] ?? '')))
  offline_reply(403, ['success'=>false,'message'=>'Refresh your sign-in before synchronising.']);
$clientId = (string)($_POST['client_submission_id'] ?? '');
if (!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/', $clientId))
  offline_reply(422, ['success'=>false,'message'=>'Missing submission ID, or the upload exceeded the server limit.']);

date_default_timezone_set('Europe/London');

ini_set('display_errors', '0'); // set to '1' while debugging
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/mail.php';
require_once __DIR__ . '/../../includes/image_utils.php'; // for HEIC-safe JPEGs

// ------- Collect inputs -------
$site_name    = trim((string)post('site_name'));
$location     = trim((string)post('location'));
$issued_at    = trim((string)post('issued_at'));
$issued_to    = trim((string)post('issued_to'));
$issued_by    = trim((string)post('issued_by'));
$reason       = (string)post('reason');
$description  = (string)post('description');
$urgency      = trim((string)post('urgency'));
$deadline_at  = trim((string)post('deadline_at'));
$completed_ok = trim((string)post('completed_ok'));
$mcgoff_clear = trim((string)post('mcgoff_clear'));
$signature    = (string)post('signature_data');

$recipients = isset($_POST['recipients']) && is_array($_POST['recipients']) ? array_values(array_filter($_POST['recipients'])) : [];
$extraEmail = isset($_POST['extra_email']) ? trim((string)$_POST['extra_email']) : '';

// Manual extra email (optional)
if ($extraEmail && filter_var($extraEmail, FILTER_VALIDATE_EMAIL)) {
  $recipients[] = $extraEmail;

  // Remember in cookie (last 10)
  $existing = isset($_COOKIE['extra_emails']) ? array_filter(explode(',', (string)$_COOKIE['extra_emails'])) : [];
  if (!in_array($extraEmail, $existing, true)) {
    $existing[] = $extraEmail;
    $existing = array_slice($existing, -10);
  }
  setcookie('extra_emails', implode(',', $existing), time() + (365*24*60*60), '/');
}

// Detect PHP upload/input limits rather than silently accepting partial photos.
if ($syncRequest) {
  $actualPhotos = !empty($_FILES['photos']['name'][0]) ? count($_FILES['photos']['name']) : 0;
  if ((int)($_POST['offline_photo_count'] ?? -1) !== $actualPhotos ||
      (int)($_POST['offline_annotation_count'] ?? -1) !== count($_POST['annotate'] ?? []))
    offline_reply(422, ['success'=>false,'message'=>'The server did not receive every photo or annotation. Reduce the number or size of attachments and retry.']);
}
// Validate before saving any record or receipt.
$recipients = array_values(array_unique(array_filter($recipients, static fn($email) => is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL))));
if (!$site_name || !$location || !$issued_at || !$issued_to || !$issued_by || !$description || empty($recipients) || !$signature) {
  offline_reply(422, ['success'=>false,'message'=>'Complete the required fields, signature and recipients.']);
}
foreach ([$issued_at, $deadline_at] as $date) {
  if ($date === '') continue;
  $format = strlen($date) === 16 ? '!Y-m-d\TH:i' : '!Y-m-d\TH:i:s';
  $parsed = DateTimeImmutable::createFromFormat($format, $date);
  $dateErrors = DateTimeImmutable::getLastErrors();
  if (!$parsed || ($dateErrors && ($dateErrors['warning_count'] || $dateErrors['error_count'])))
    offline_reply(422, ['success'=>false,'message'=>'Invalid date or time.']);
}
$filesDir = '';
try {
  offline_schema($pdo);
  $payloadHash = offline_payload_hash($_POST, $_FILES);
  $receipt = offline_receipt($pdo, (string)$_SESSION['user'], $clientId);
  if ($receipt) {
    if (!hash_equals($receipt['payload_hash'], $payloadHash)) offline_reply(409, ['success'=>false,'message'=>'This saved submission ID was already used for different data.']);
    $notice_id = (int)$receipt['notice_id'];
    $pdfPath = __DIR__ . '/../../uploads/cleanup/'.$notice_id.'/clean-up-notice-'.$notice_id.'.pdf';
    goto SEND_NOTICE_MAIL;
  }
  // The receipt and notice commit together, including attachment paths and PDF.
  $pdo->beginTransaction();
  $claim = $pdo->prepare('INSERT INTO notice_submission_receipts (user_name, client_id, payload_hash, recipient_count) VALUES (?, ?, ?, ?)');
  $claim->execute([$_SESSION['user'], $clientId, $payloadHash, count($recipients)]);
// ------- Insert main record -------
  $stmt = $pdo->prepare("
    INSERT INTO cleanup_notices
      (user_name, site_name, location, issued_at, issued_to, issued_by, reason, description, urgency, deadline_at, completed_ok, mcgoff_clear, created_at)
    VALUES
      (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
  ");
  $stmt->execute([
    $_SESSION['user'], $site_name, $location, $issued_at, $issued_to,
    $issued_by, $reason, $description, $urgency, $deadline_at ?: null,
    $completed_ok, $mcgoff_clear
  ]);
  $notice_id = (int)$pdo->lastInsertId();
// ------- Prepare upload directory -------
$filesDir = __DIR__ . '/../../uploads/cleanup/' . $notice_id;
if (!is_dir($filesDir) && !@mkdir($filesDir, 0775, true)) {
  throw new RuntimeException('Unable to create the upload directory.');
}

// ------- Process standard file uploads (photos[]) -------
$photoPaths = [];
if (!empty($_FILES['photos']['name'][0])) {
  $count = count($_FILES['photos']['name']);
  for ($i = 0; $i < $count; $i++) {
    $orig = $_FILES['photos']['name'][$i] ?? '';
    $tmp  = $_FILES['photos']['tmp_name'][$i] ?? '';
    if (!$orig || !$tmp) continue;

    // Convert anything (incl. HEIC) -> rotated, flattened JPEG
    $saved = process_uploaded_image($tmp, $filesDir, 'photo_'.$i.'_'.bin2hex(random_bytes(4)).'_'.$orig, 2000, 2000, 82);
    if (!$saved) throw new InvalidArgumentException('A photo could not be processed. Use JPEG or PNG, then retry.');
    $photoPaths[] = $saved;
  }
}

// ------- Process annotated images (optional) -------
if (!empty($_POST['annotate']) && is_array($_POST['annotate'])) {
  foreach ($_POST['annotate'] as $idx => $dataUrl) {
    $dataUrl = trim((string)$dataUrl);
    if ($dataUrl === '') continue;
    $saved = process_base64_image($dataUrl, $filesDir, 'annotated_'.$idx, 2000, 2000, 82);
    if (!$saved) throw new InvalidArgumentException('An annotated image could not be processed.');
    $photoPaths[] = $saved;
  }
}

// ------- Save signature (flatten to JPEG) -------
$sigAbs = '';
if ($signature) {
  $savedSig = process_base64_image($signature, $filesDir, 'signature', 1200, 1200, 85);
  if ($savedSig) {
    $sigAbs = $savedSig;
  } else throw new InvalidArgumentException('The signature could not be processed.');
}

// ------- Update record with signature path (PDF path added later) -------
try {
  $upd = $pdo->prepare("UPDATE cleanup_notices SET signature_path = ? WHERE id = ?");
  $upd->execute([$sigAbs ?: null, $notice_id]);
} catch (Throwable $e) {
  throw $e;
}

// ------- Save photo rows to cleanup_photos -------
if (!empty($photoPaths)) {
  try {
    $ins = $pdo->prepare("INSERT INTO cleanup_photos (notice_id, path) VALUES (?, ?)");
    foreach ($photoPaths as $p) {
      $ins->execute([$notice_id, $p]);
    }
  } catch (Throwable $e) {
    throw $e;
  }
}

// ------- Generate PDF by capturing pdf.php output -------
$pdfPath = $filesDir . '/clean-up-notice-' . $notice_id . '.pdf';
try {
  define('PDF_CAPTURE_MODE', true);
  $NOTICE_ID = $notice_id;

  $bufferLevel = ob_get_level();
  ob_start();
  // pdf.php will echo the PDF bytes when PDF_CAPTURE_MODE is true
  include __DIR__ . '/pdf.php';
  $pdfBytes = ob_get_clean();

  if (!$pdfBytes || strlen($pdfBytes) < 50 || strncmp($pdfBytes, '%PDF-', 5) !== 0) {
    throw new RuntimeException('Empty PDF output.');
  }
  if (file_put_contents($pdfPath, $pdfBytes) === false) {
    throw new RuntimeException('Failed to write PDF file.');
  }

  // Save pdf_path
  $upd2 = $pdo->prepare("UPDATE cleanup_notices SET pdf_path = ? WHERE id = ?");
  $upd2->execute([$pdfPath, $notice_id]);

} catch (Throwable $e) {
  while (ob_get_level() > ($bufferLevel ?? ob_get_level())) ob_end_clean();
  throw $e;
}

$receiptUpdate = $pdo->prepare('UPDATE notice_submission_receipts SET notice_id = ? WHERE user_name = ? AND client_id = ?');
$receiptUpdate->execute([$notice_id, $_SESSION['user'], $clientId]);
$pdo->commit();
} catch (Throwable $error) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  if ($filesDir !== '') offline_cleanup_directory($filesDir);
  // Concurrent replays converge on the already committed receipt.
  $receipt = isset($payloadHash) ? offline_receipt($pdo, (string)$_SESSION['user'], $clientId) : null;
  if ($receipt && !hash_equals($receipt['payload_hash'], $payloadHash)) offline_reply(409, ['success'=>false,'message'=>'This submission ID already belongs to different notice data.']);
  if ($receipt && hash_equals($receipt['payload_hash'], $payloadHash)) {
    $notice_id = (int)$receipt['notice_id'];
    $pdfPath = __DIR__ . '/../../uploads/cleanup/'.$notice_id.'/clean-up-notice-'.$notice_id.'.pdf';
    goto SEND_NOTICE_MAIL;
  }
  error_log('Notice sync: '.$error->getMessage());
  offline_reply($error instanceof InvalidArgumentException ? 422 : 503, ['success'=>false,'message'=>
    $error instanceof InvalidArgumentException ? $error->getMessage() : 'Unable to save this notice yet. Your device will retain it and retry.']);
}

SEND_NOTICE_MAIL:
// Release the session lock while SMTP runs so other tabs can refresh or sign in.
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
// Only the request that claims pending mail may send. Replays never send twice.
// A crash during SMTP delivery is explicitly reported as uncertain.
$claimMail = $pdo->prepare("UPDATE notice_submission_receipts SET mail_state = 'sending' WHERE user_name = ? AND client_id = ? AND mail_state = 'pending'");
$claimMail->execute([$_SESSION['user'], $clientId]);
if ($claimMail->rowCount() === 1) {
  $mailState = 'failed';
  try {
    $subject = "Clean-Up Notice – {$site_name}";
    $body = '<p>A new Clean-Up Notice has been submitted.</p><p><strong>Site:</strong> '.esc($site_name).
      '<br><strong>Issued To:</strong> '.esc($issued_to).'<br><strong>Urgency:</strong> '.esc($urgency).'</p><p>The full notice is attached as a PDF.</p>';
    $result = send_mail($recipients, $subject, $body, [['path'=>$pdfPath,'name'=>basename($pdfPath)]]);
    $mailState = !empty($result['ok']) ? 'sent' : 'failed';
  } catch (Throwable $error) {
    error_log('Notice email: '.$error->getMessage());
    $mailState = 'uncertain';
  }
  $markMail = $pdo->prepare('UPDATE notice_submission_receipts SET mail_state = ? WHERE user_name = ? AND client_id = ?');
  $markMail->execute([$mailState, $_SESSION['user'], $clientId]);
}
// Remember valid recipients only after the notice is saved.
try {
  $insR = $pdo->prepare('INSERT IGNORE INTO recipient_emails (email) VALUES (?)');
  foreach ($recipients as $email) $insR->execute([$email]);
} catch (Throwable $error) { error_log('Recipient save: '.$error->getMessage()); }
$receipt = offline_receipt($pdo, (string)$_SESSION['user'], $clientId);
if ($syncRequest) offline_reply(200, offline_receipt_body($receipt));
$qs = http_build_query(['id'=>$notice_id, 'sent'=>$receipt['mail_state'] === 'sent' ? 1 : 0, 'rc'=>count($recipients)]);
header('Location: success.php?'.$qs);
exit;
