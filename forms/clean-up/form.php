<?php
// /forms/clean-up/form.php
declare(strict_types=1);
session_start();
if (empty($_SESSION['offline_csrf'])) $_SESSION['offline_csrf'] = bin2hex(random_bytes(32));
if (!isset($_SESSION['user'])) { header('Location: /index.php'); exit; }

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';

date_default_timezone_set('Europe/London');
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<link rel="stylesheet" href="/assets/css/notice-form.css?v=2">
<main class="container docs-form-page">
  <h1>New Clean-Up Notice</h1><p class="docs-muted">Your work is saved on this device. Submit to queue it for upload when signal returns.</p><p id="formSaveStatus" role="status" aria-live="polite"></p>
  <form method="post" action="submit.php" enctype="multipart/form-data" id="noticeForm" onsubmit="return beforeSubmit();">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['offline_csrf'], ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="client_submission_id" value="<?= sprintf('%s-%s-4%s-%s-%s', bin2hex(random_bytes(4)), bin2hex(random_bytes(2)), substr(bin2hex(random_bytes(2)),1), dechex(random_int(8,11)).substr(bin2hex(random_bytes(2)),1), bin2hex(random_bytes(6))) ?>">
    <div class="form-row"><label for="site_name">Site Name *</label><input type="text" id="site_name" name="site_name" required></div>
    <div class="form-row"><label for="location">Location *</label><input type="text" id="location" name="location" required></div>
    <div class="form-row"><label for="issued_at">Date / Time Issued *</label><input type="datetime-local" id="issued_at" name="issued_at" required></div>
    <div class="form-row"><label for="issued_to">Issued To *</label><input type="text" id="issued_to" name="issued_to" required></div>
    <div class="form-row"><label for="issued_by">Issued By *</label><input type="text" id="issued_by" name="issued_by" required></div>
    <div class="form-row"><label for="reason">Reason</label><textarea id="reason" name="reason" rows="2"></textarea></div>
    <div class="form-row"><label for="description">Description *</label><textarea id="description" name="description" rows="3" required></textarea></div>
    <div class="form-row">
      <label for="urgency">Urgency *</label>
      <select id="urgency" name="urgency" required onchange="autoSetDeadline()">
        <option value="">— Select —</option>
        <option value="Immediate">Immediate</option>
        <option value="Within 1 hour">Within 1 hour</option>
        <option value="Same day">Same day</option>
        <option value="Within 24 hours">Within 24 hours</option>
        <option value="This week">This week</option>
      </select>
    </div>
    <div class="form-row"><label for="deadline_at">Deadline</label><input type="datetime-local" id="deadline_at" name="deadline_at"></div>
    <div class="form-row"><label for="completed_ok">Completed OK? *</label>
      <select id="completed_ok" name="completed_ok" required>
        <option value="">— Select —</option>
        <option value="yes">Yes</option>
        <option value="no">No</option>
      </select>
    </div>
    <div class="form-row"><label for="mcgoff_clear">Main contractor to arrange clearance? *</label>
      <select id="mcgoff_clear" name="mcgoff_clear" required>
        <option value="">— Select —</option>
        <option value="yes">Yes</option>
        <option value="no">No</option>
      </select>
    </div>

    <div class="form-row"><label for="photos">Upload Photos</label><input type="file" id="photos" name="photos[]" accept="image/*;capture=camera" multiple><div id="photoThumbs" class="thumbs"></div></div>

    <div class="form-row">
      <label for="annotate_file">Annotate (optional)</label>
      <input type="file" id="annotate_file" accept="image/*">
      <div class="toolbar">
        <label>Pen: <input type="color" id="penColor" value="#e11d48"></label>
        <label>Size: <input type="range" id="penSize" min="2" max="24" value="4"></label>
        <button type="button" onclick="clearAnnotation()">Clear</button>
        <button type="button" onclick="addAnnotation()">Add annotated image</button>
      </div>
      <canvas id="annCanvas" width="600" height="400"></canvas>
      <div id="annThumbs" class="thumbs"></div>
      <div id="annFields"></div>
    </div>

    <div class="form-row">
      <label>Signature *</label>
      <canvas id="sigCanvas" width="600" height="200"></canvas>
      <div class="toolbar"><button type="button" onclick="clearSignature()">Clear Signature</button></div>
      <input type="hidden" id="signature_data" name="signature_data">
    </div>

    <div class="form-row">
      <label>Recipients *</label>
      <select id="recipients" name="recipients[]" multiple required></select>
      <input type="email" id="extra_email" placeholder="Add recipient email...">
      <button type="button" class="btn" onclick="addRecipient()">+ Add Email</button>
    </div>

    <div class="form-row">
      <label>Tap to add previous recipients:</label>
      <div id="recipientChips"></div>
    </div>

    <div class="form-row offline-actions"><button type="submit" class="btn">Submit Notice</button><button type="button" id="saveDraftNow" class="btn-secondary">Save draft on device</button></div>
  </form>
</main>

<script defer src="/assets/js/notice-form.js?v=2"></script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
