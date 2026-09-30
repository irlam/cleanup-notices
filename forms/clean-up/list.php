<?php
// /forms/clean-up/list.php – Clean-Up Notice list with close-out
declare(strict_types=1);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
if (!isset($_SESSION['user'])) { header('Location: /index.php'); exit; }

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/header.php';

$site      = trim($_GET['site'] ?? '');
$issued_to = trim($_GET['issued_to'] ?? '');
$status    = $_GET['status'] ?? 'all';   // all|open|closed
$from      = trim($_GET['from'] ?? '');
$to        = trim($_GET['to'] ?? '');

// WHERE
$clauses = [];
$params  = [];
if ($site !== '') {
  $clauses[] = 'site_name LIKE :site';
  $params[':site'] = "%$site%";
}
if ($issued_to !== '') {
  $clauses[] = 'issued_to LIKE :issued_to';
  $params[':issued_to'] = "%$issued_to%";
}
if ($status === 'open') {
  $clauses[] = "status = 'open'";
} elseif ($status === 'closed') {
  $clauses[] = "status = 'closed'";
}
if ($from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
  $clauses[] = 'DATE(created_at) >= :from';
  $params[':from'] = $from;
}
if ($to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
  $clauses[] = 'DATE(created_at) <= :to';
  $params[':to'] = $to;
}
$whereSQL = $clauses ? 'WHERE '.implode(' AND ', $clauses) : '';

// Get rows (limit for performance)
$stmt = $pdo->prepare("SELECT * FROM cleanup_notices $whereSQL ORDER BY id DESC LIMIT 500");
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// KPI stats from the filtered set
$total     = count($rows);
$completed = count(array_filter($rows, fn($r) => strtolower(trim($r['completed_ok'] ?? '')) === 'yes'));
$pending   = count(array_filter($rows, fn($r) => !in_array(strtolower(trim($r['completed_ok'] ?? '')), ['yes'])));
$openCnt   = count(array_filter($rows, fn($r) => ($r['status'] ?? 'open') === 'open'));
$closedCnt = count($rows) - $openCnt;
?>
<div class="hero-grad border-b">
  <div class="max-w-6xl mx-auto px-4 py-8 md:py-12">
    <div class="flex items-start md:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl md:text-3xl font-bold text-white">Clean-Up Notices</h1>
        <p class="text-slate-200 mt-1">Browse, filter, close, and open Clean-Up Notices.</p>
        <div class="mt-3 flex flex-wrap gap-2 text-slate-200">
          <span class="chip">User: <strong><?php echo esc($_SESSION['user']); ?></strong></span>
          <span class="chip">Results: <strong><?php echo (int)$total; ?></strong></span>
          <?php if (isset($_GET['closed'])): ?>
            <span class="chip docs-status-success">Marked as closed.</span>
          <?php endif; ?>
        </div>
      </div>
      <div class="flex items-center gap-2">
        <a href="/forms/clean-up/form.php" class="cta rounded-lg px-4 py-2 text-sm font-semibold bg-blue-600 text-white hover:bg-blue-700">
  New Clean-Up Notice
</a>
      </div>
    </div>
  </div>
</div>

<div class="max-w-6xl mx-auto px-4 py-6 space-y-6">

  <!-- Filters -->
  <form class="card p-4 space-y-4" method="get">
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-4">
      <div>
        <label class="block text-sm font-medium">Site</label>
        <input type="text" name="site" value="<?php echo esc($site); ?>" class="form-input w-full">
      </div>
      <div>
        <label class="block text-sm font-medium">Issued To</label>
        <input type="text" name="issued_to" value="<?php echo esc($issued_to); ?>" class="form-input w-full">
      </div>
      <div>
        <label class="block text-sm font-medium">From</label>
        <input type="date" name="from" value="<?php echo esc($from); ?>" class="form-input w-full">
      </div>
      <div>
        <label class="block text-sm font-medium">To</label>
        <input type="date" name="to" value="<?php echo esc($to); ?>" class="form-input w-full">
      </div>
      <div>
        <label class="block text-sm font-medium">Status</label>
        <select name="status" class="form-select w-full">
          <option value="all" <?php if ($status==='all') echo 'selected'; ?>>All</option>
          <option value="open" <?php if ($status==='open') echo 'selected'; ?>>Open</option>
          <option value="closed" <?php if ($status==='closed') echo 'selected'; ?>>Closed</option>
        </select>
      </div>
    </div>
    <div class="flex flex-wrap gap-2 items-end">
      <button type="submit" class="btn-primary">Apply Filters</button>
      <a href="list.php" class="btn-secondary">Reset</a>
    </div>
  </form>

  <!-- KPIs -->
  <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 justify-center">
    <a href="?status=all" class="card kpi kpi--blue hover:shadow-md transition">
      <div class="kpi-badge">Total</div>
      <div class="text-sm text-slate-200">Filtered</div>
      <div class="text-3xl font-bold mt-1 text-white"><?php echo $total; ?></div>
    </a>
    <a href="?status=open" class="card kpi kpi--cyan hover:shadow-md transition">
      <div class="kpi-badge">Open</div>
      <div class="text-sm text-slate-200">Awaiting close</div>
      <div class="text-3xl font-bold mt-1 text-white"><?php echo $openCnt; ?></div>
    </a>
    <a href="?status=closed" class="card kpi kpi--emerald hover:shadow-md transition">
      <div class="kpi-badge">Closed</div>
      <div class="text-sm text-slate-200">Closed out</div>
      <div class="text-3xl font-bold mt-1 text-white"><?php echo $closedCnt; ?></div>
    </a>
    <div class="card kpi kpi--amber">
      <div class="kpi-badge">Completed</div>
      <div class="text-sm text-slate-200">Marked Yes</div>
      <div class="text-3xl font-bold mt-1 text-white"><?php echo $completed; ?></div>
    </div>
  </div>

  <!-- Table -->
  <div class="overflow-x-auto">
    <table class="table w-full text-sm">
      <thead>
        <tr>
          <th>ID</th>
          <th>Site</th>
          <th>Location</th>
          <th>Issued To</th>
          <th>Issued At</th>
          <th>Status</th>
          <th>PDF</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr class="docs-table-row">
            <td><?php echo (int)$r['id']; ?></td>
            <td><?php echo esc($r['site_name']); ?></td>
            <td><?php echo esc($r['location']); ?></td>
            <td><?php echo esc($r['issued_to']); ?></td>
            <td><?php echo esc(date('d/m/Y H:i', strtotime($r['issued_at']))); ?></td>
            <td>
              <?php if (($r['status'] ?? 'open') === 'closed'): ?>
                <span class="chip docs-status-closed">Closed</span>
              <?php else: ?>
                <span class="chip docs-status-open">Open</span>
              <?php endif; ?>
            </td>
            <td><a href="/forms/clean-up/pdf.php?id=<?php echo (int)$r['id']; ?>" class="text-blue-600 hover:underline" target="_blank">PDF</a></td>
            <td>
              <?php if (($r['status'] ?? 'open') === 'open'): ?>
                <a href="close.php?id=<?php echo (int)$r['id']; ?>"
                   class="btn-secondary"
                   onclick="return confirm('Mark notice #<?php echo (int)$r['id']; ?> as closed?');">Close</a>
              <?php else: ?>
                <span class="text-slate-400">—</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

