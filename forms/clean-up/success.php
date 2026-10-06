<?php
// /forms/clean-up/success.php
declare(strict_types=1);
session_start();
if (!isset($_SESSION['user'])) { header('Location: /index.php'); exit; }

date_default_timezone_set('Europe/London');

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

$id  = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$rc  = isset($_GET['rc']) ? (int)$_GET['rc'] : 0;   // recipients count
$sent = isset($_GET['sent']) ? (int)$_GET['sent'] : 0;

if ($id <= 0) { http_response_code(400); echo 'Missing ID.'; exit; }

$stmt = $pdo->prepare("SELECT * FROM cleanup_notices WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $id]);
$notice = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$notice) { http_response_code(404); echo 'Notice not found.'; exit; }

// UK datetime formatter
function ukdt(?string $s): string {
  if (!$s || $s === '0000-00-00 00:00:00') return 'N/A';
  $ts = strtotime($s);
  return $ts ? date('d/m/Y H:i', $ts) : $s;
}
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>

<div class="max-w-6xl mx-auto px-4 py-6 space-y-6">
  <?php if ($sent && $rc >= 0): ?>
    <div class="card" style="border-left:4px solid #16a34a">
      <strong>Success:</strong>
      Notice submitted and emailed to <strong><?php echo (int)$rc; ?></strong> recipient<?php echo $rc==1?'':'s'; ?>.
      Reference #<?php echo (int)$id; ?>.
    </div>
  <?php endif; ?>

  <div class="card">
    <h1 class="text-2xl font-bold mb-2">Clean-Up Notice #<?php echo (int)$id; ?></h1>
    <div class="text-sm text-slate-600 mb-4">
      <span>Created: <?php echo ukdt($notice['created_at'] ?? null); ?></span> ·
      <span>Issued At: <?php echo ukdt($notice['issued_at'] ?? null); ?></span>
      <?php if (!empty($notice['deadline_at'])): ?>
        · <span>Deadline: <?php echo ukdt($notice['deadline_at']); ?></span>
      <?php endif; ?>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <div><strong>Site:</strong> <?php echo esc($notice['site_name'] ?? ''); ?></div>
        <div><strong>Location:</strong> <?php echo esc($notice['location'] ?? ''); ?></div>
        <div><strong>Issued To:</strong> <?php echo esc($notice['issued_to'] ?? ''); ?></div>
        <div><strong>Issued By:</strong> <?php echo esc($notice['issued_by'] ?? ''); ?></div>
      </div>
      <div>
        <div><strong>Urgency:</strong> <?php echo esc($notice['urgency'] ?? ''); ?></div>
        <div><strong>Completed OK?</strong> <?php echo esc($notice['completed_ok'] ?? ''); ?></div>
        <div><strong>Main contractor to arrange clearance?</strong> <?php echo esc($notice['mcgoff_clear'] ?? ''); ?></div>
      </div>
    </div>

    <div class="mt-4 flex flex-wrap gap-2">
      <a class="btn-primary" href="/forms/clean-up/pdf.php?id=<?php echo (int)$id; ?>" target="_blank">View PDF</a>
      <a class="btn-secondary" href="/forms/clean-up/pdf.php?id=<?php echo (int)$id; ?>&dl=1">Download PDF</a>
      <a class="btn-secondary" href="/forms/clean-up/list.php">Back to List</a>
      <a class="btn-secondary" href="/dashboard.php">Dashboard</a>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

