<?php
// forms/clean-up/submit.php
declare(strict_types=1);

session_start();
if (!isset($_SESSION['user'])) { header('Location: /index.php'); exit; }

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

// ------- Save unique recipients to DB (for future autocomplete) -------
try {
  $insR = $pdo->prepare("INSERT IGNORE INTO recipient_emails (email) VALUES (?)");
  foreach ($recipients as $email) {
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $insR->execute([$email]);
    }
  }
} catch (Throwable $e) {
  error_log('submit.php recipient_emails insert error: '.$e->getMessage());
}

// ------- Basic validation -------
if (!$site_name || !$location || !$issued_at || !$issued_to || !$issued_by || !$description || empty($recipients)) {
  http_response_code(422);
  echo 'Missing required fields. <a href="form.php">Back</a>';
  exit;
}

// ------- Insert main record -------
try {
  $stmt = $pdo->prepare("
    INSERT INTO cleanup_notices
      (user_name, site_name, location, issued_at, issued_to, issued_by, reason, description, urgency, deadline_at, completed_ok, mcgoff_clear, created_at)
    VALUES
      (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
  ");
  $stmt->execute([
    $_SESSION['user'], $site_name, $location, $issued_at, $issued_to,
    $issued_by, $reason, $description, $urgency, $deadline_at,
    $completed_ok, $mcgoff_clear
  ]);
  $notice_id = (int)$pdo->lastInsertId();
} catch (Throwable $e) {
  http_response_code(500);
  echo 'DB insert failed.';
  error_log('submit.php insert error: '.$e->getMessage());
  exit;
}

// ------- Prepare upload directory -------
$filesDir = __DIR__ . '/../../uploads/cleanup/' . $notice_id;
if (!is_dir($filesDir) && !@mkdir($filesDir, 0775, true)) {
  http_response_code(500);
  echo 'Failed to create upload directory.';
  exit;
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
    $saved = process_uploaded_image($tmp, $filesDir, $orig, 2000, 2000, 82);
    if ($saved) {
      $photoPaths[] = $saved; // absolute path
    }
  }
}

// ------- Process annotated images (optional) -------
if (!empty($_POST['annotate']) && is_array($_POST['annotate'])) {
  foreach ($_POST['annotate'] as $idx => $dataUrl) {
    $dataUrl = trim((string)$dataUrl);
    if ($dataUrl === '') continue;
    $saved = process_base64_image($dataUrl, $filesDir, 'annotated_'.$idx, 2000, 2000, 82);
    if ($saved) {
      $photoPaths[] = $saved;
    }
  }
}

// ------- Save signature (flatten to JPEG) -------
$sigAbs = '';
if ($signature) {
  $savedSig = process_base64_image($signature, $filesDir, 'signature', 1200, 1200, 85);
  if ($savedSig) {
    $sigAbs = $savedSig;
  }
}

// ------- Update record with signature path (PDF path added later) -------
try {
  $upd = $pdo->prepare("UPDATE cleanup_notices SET signature_path = ? WHERE id = ?");
  $upd->execute([$sigAbs ?: null, $notice_id]);
} catch (Throwable $e) {
  // Non-fatal
  error_log('submit.php signature update error: '.$e->getMessage());
}

// ------- Save photo rows to cleanup_photos -------
if (!empty($photoPaths)) {
  try {
    $ins = $pdo->prepare("INSERT INTO cleanup_photos (notice_id, path) VALUES (?, ?)");
    foreach ($photoPaths as $p) {
      $ins->execute([$notice_id, $p]);
    }
  } catch (Throwable $e) {
    // Non-fatal
    error_log('submit.php photos insert error: '.$e->getMessage());
  }
}

// ------- Generate PDF by capturing pdf.php output -------
$pdfPath = $filesDir . '/clean-up-notice-' . $notice_id . '.pdf';
try {
  define('PDF_CAPTURE_MODE', true);
  $NOTICE_ID = $notice_id;

  ob_start();
  // pdf.php will echo the PDF bytes when PDF_CAPTURE_MODE is true
  include __DIR__ . '/pdf.php';
  $pdfBytes = ob_get_clean();

  if (!$pdfBytes || strlen($pdfBytes) < 50) {
    throw new RuntimeException('Empty PDF output.');
  }
  if (file_put_contents($pdfPath, $pdfBytes) === false) {
    throw new RuntimeException('Failed to write PDF file.');
  }

  // Save pdf_path
  $upd2 = $pdo->prepare("UPDATE cleanup_notices SET pdf_path = ? WHERE id = ?");
  $upd2->execute([$pdfPath, $notice_id]);

} catch (Throwable $e) {
  // If PDF failed, continue but log it; email step will skip attach if missing
  error_log('submit.php PDF generation error: '.$e->getMessage());
  $pdfPath = '';
}

// ------- Email recipients with PDF attachment -------
$rc = count($recipients);
if ($rc > 0) {
  $subject = "Clean-Up Notice – {$site_name}";
  $body    = "
    <p>A new Clean-Up Notice has been submitted.</p>
    <p>
      <strong>Site:</strong> " . esc($site_name) . "<br>
      <strong>Issued To:</strong> " . esc($issued_to) . "<br>
      <strong>Urgency:</strong> " . esc($urgency) . "<br>" .
      ($deadline_at ? "<strong>Deadline:</strong> " . esc($deadline_at) . "<br>" : "") .
    "</p>
    <p>The full notice is attached as a PDF.</p>
  ";

  try {
    if ($pdfPath && is_readable($pdfPath)) {
      send_mail($recipients, $subject, $body, [
        ['path' => $pdfPath, 'name' => basename($pdfPath)]
      ]);
    } else {
      // Fallback: send without attachment
      send_mail($recipients, $subject, $body);
    }
  } catch (Throwable $e) {
    error_log('submit.php email error: '.$e->getMessage());
  }
}

// ------- Redirect to success page -------
$qs = http_build_query([
  'id'   => $notice_id,
  'sent' => 1,
  'rc'   => $rc
]);
header("Location: success.php?{$qs}");
exit;
