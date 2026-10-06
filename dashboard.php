<?php
// dashboard.php (with sparkline + coloured KPIs, styled properly)
session_start();
if (!isset($_SESSION['user'])) { header('Location: index.php'); exit; }
ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ALL);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/header.php';

// Ensure user_role is set
if (!isset($_SESSION['user_role'])) {
    $stmt = $pdo->prepare("SELECT role FROM users WHERE username = ?");
    $stmt->execute([$_SESSION['user']]);
    $role = $stmt->fetchColumn();
    $_SESSION['user_role'] = $role ?: 'user';
}

/** Build last-7-days counts including zero days */
function get_last7_counts(PDO $pdo): array {
  $days = [];
  for ($i=6; $i>=0; $i--) {
    $d = (new DateTime("today"))->modify("-{$i} day")->format('Y-m-d');
    $days[$d] = 0;
  }
  $stmt = $pdo->query("
    SELECT DATE(created_at) d, COUNT(*) c
    FROM cleanup_notices
    WHERE created_at >= (CURDATE() - INTERVAL 6 DAY)
    GROUP BY d
  ");
  foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $d = $row['d']; $c = (int)$row['c'];
    if (isset($days[$d])) $days[$d] = $c;
  }
  return $days;
}

try {
  $total     = (int)$pdo->query("SELECT COUNT(*) FROM cleanup_notices")->fetchColumn();
  $last7     = (int)$pdo->query("SELECT COUNT(*) FROM cleanup_notices WHERE created_at >= (NOW() - INTERVAL 7 DAY)")->fetchColumn();
  $today     = (int)$pdo->query("SELECT COUNT(*) FROM cleanup_notices WHERE DATE(created_at) = CURDATE()")->fetchColumn();
  $pending   = (int)$pdo->query("SELECT COUNT(*) FROM cleanup_notices WHERE completed_ok IS NULL OR completed_ok = '' OR LOWER(completed_ok) = 'no'")->fetchColumn();
  $completed = (int)$pdo->query("SELECT COUNT(*) FROM cleanup_notices WHERE LOWER(completed_ok) = 'yes'")->fetchColumn();

  $recentStmt = $pdo->query("SELECT id, site_name, location, issued_at, issued_to
                             FROM cleanup_notices
                             ORDER BY id DESC
                             LIMIT 5");
  $recent = $recentStmt->fetchAll(PDO::FETCH_ASSOC);

  $topStmt = $pdo->query("SELECT site_name, COUNT(*) AS cnt
                          FROM cleanup_notices
                          WHERE created_at >= (NOW() - INTERVAL 30 DAY)
                          GROUP BY site_name
                          ORDER BY cnt DESC, site_name ASC
                          LIMIT 5");
  $topSites = $topStmt->fetchAll(PDO::FETCH_ASSOC);

  $series = get_last7_counts($pdo);
} catch (Exception $e) {
  $total=$last7=$today=$pending=$completed=0; $recent=$topSites=[]; $series=[];
}

function sparkline_svg(array $series, int $w=260, int $h=60, int $pad=6): string {
  if (empty($series)) return '';
  $vals = array_values($series);
  $max  = max(1, max($vals));
  $min  = min(0, min($vals));
  $n = count($vals);
  $plotW = $w - 2*$pad; $plotH = $h - 2*$pad;
  $dx = ($n>1) ? $plotW/($n-1) : 0;

  $d = '';
  $pts = [];
  for ($i=0; $i<$n; $i++) {
    $x = $pad + $i*$dx;
    $ratio = ($max === $min) ? 0.5 : ($vals[$i]-$min)/($max-$min);
    $y = $pad + (1.0 - $ratio) * $plotH;
    $d .= ($i===0 ? "M" : "L") . round($x,1) . "," . round($y,1) . " ";
    $pts[] = ['x'=>$x,'y'=>$y];
  }

  $area = "M".round($pad,1).",".round($pts[0]['y'],1)." ";
  for ($i=1; $i<$n; $i++) {
    $area .= "L".round($pts[$i]['x'],1).",".round($pts[$i]['y'],1)." ";
  }
  $area .= "L".round($pad+$plotW,1).",".round($pad+$plotH,1)." ";
  $area .= "L".round($pad,1).",".round($pad+$plotH,1)." Z";

  return '<svg aria-label="Last 7 days" class="sparkline" height="'.$h.'" role="img" style="overflow: hidden; display: block; width: 100%; height: 100%;" viewBox="0 0 '.$w.' '.$h.'" width="'.$w.'">
    <path class="area" d="'.$area.'" style="fill: rgba(255,255,255,0.2); stroke: none;"></path>
    <path class="line" d="'.$d.'" style="fill: none; stroke: #ffffff; stroke-width: 2;"></path>
  </svg>';
}
?>

<div class="hero-grad border-b">
  <div class="max-w-6xl mx-auto px-4 py-8 md:py-12">
    <div class="flex items-start md:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl md:text-3xl font-bold text-white">Dashboard</h1>
        <p class="text-slate-200 mt-1">Quick actions and a snapshot of activity across site.</p>
        <div class="mt-3 flex flex-wrap gap-2 text-slate-200">
          <span class="chip">User: <strong class="font-medium"><?php echo esc($_SESSION['user']); ?></strong></span>
          <span class="chip">Today: <strong class="font-medium"><?php echo date('d-m-Y'); ?></strong></span>
        </div>
      </div>
      <div class="flex items-center gap-2 flex-wrap">
        <a href="/forms/clean-up/form.php" class="cta rounded-lg px-4 py-2 text-sm font-semibold bg-blue-600 text-white hover:bg-blue-700">
          New Clean-Up Notice
        </a>
        <a href="/forms/clean-up/list.php" class="cta rounded-lg px-4 py-2 text-sm font-semibold bg-blue-600 text-white hover:bg-blue-700">
          View Notices
        </a>
        <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
        <a href="/tools/add_user.php" class="cta rounded-lg px-4 py-2 text-sm font-semibold bg-blue-600 text-white hover:bg-blue-700">
          ➕ Add New User
        </a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="max-w-6xl mx-auto px-4 py-6 md:py-8 space-y-6">

  <!-- KPIs -->
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
    <div class="card kpi kpi--blue rounded-xl p-4">
      <div class="kpi-badge">All time</div>
      <div class="text-sm">Total Notices</div>
      <div class="text-3xl font-bold mt-1"><?php echo (int)$total; ?></div>
    </div>

    <div class="card kpi kpi--cyan rounded-xl p-4">
      <div class="kpi-badge">Last 7d</div>
      <div class="text-sm flex items-center justify-between">
        <span>Last 7 Days</span>
        <span class="ml-2"><?php echo sparkline_svg($series); ?></span>
      </div>
      <div class="text-3xl font-bold mt-1"><?php echo (int)$last7; ?></div>
    </div>

    <div class="card kpi kpi--amber rounded-xl p-4">
      <div class="kpi-badge">Today</div>
      <div class="text-sm">Today</div>
      <div class="text-3xl font-bold mt-1"><?php echo (int)$today; ?></div>
      <div class="mt-3 kpi-chip">Since midnight</div>
    </div>

    <div class="card kpi kpi--rose rounded-xl p-4">
      <div class="kpi-badge">Attention</div>
      <div class="text-sm">Pending*</div>
      <div class="text-3xl font-bold mt-1"><?php echo (int)$pending; ?></div>
      <div class="mt-2 text-[11px]">*No completion recorded or marked “no”.</div>
    </div>

    <div class="card kpi kpi--emerald rounded-xl p-4">
      <div class="kpi-badge">Resolved</div>
      <div class="text-sm">Completed/Closed</div>
      <div class="text-3xl font-bold mt-1"><?php echo (int)$completed; ?></div>
      <div class="mt-3 kpi-chip">Marked “yes”</div>
    </div>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Recent Notices -->
    <div class="card rounded-xl overflow-hidden">
      <div class="px-4 py-3 border-b docs-panel-header">
        <h2 class="text-lg font-semibold text-slate-100">Recent Notices</h2>
      </div>
      <div class="p-4 overflow-x-auto">
        <?php if(empty($recent)): ?>
          <div class="text-slate-500 text-sm">No notices yet.</div>
        <?php else: ?>
          <table class="table min-w-full text-sm">
            <thead>
              <tr>
                <th class="text-left p-2">#</th>
                <th class="text-left p-2">Site</th>
                <th class="text-left p-2">Location</th>
                <th class="text-left p-2">Issued At</th>
                <th class="text-left p-2">Issued To</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($recent as $r): ?>
                <tr class="docs-table-row">
                  <td class="p-2"><?php echo (int)$r['id']; ?></td>
                  <td class="p-2"><?php echo esc($r['site_name']); ?></td>
                  <td class="p-2"><?php echo esc($r['location']); ?></td>
                  <td class="p-2"><?php echo esc($r['issued_at']); ?></td>
                  <td class="p-2"><?php echo esc($r['issued_to']); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>

    <!-- Top Sites -->
    <div class="card rounded-xl overflow-hidden">
      <div class="px-4 py-3 border-b docs-panel-header">
        <h2 class="text-lg font-semibold text-slate-100">Top Sites (Last 30 Days)</h2>
      </div>
      <div class="p-4">
        <?php if(empty($topSites)): ?>
          <div class="text-slate-500 text-sm">No activity in the last 30 days.</div>
        <?php else: ?>
          <table class="table min-w-full text-sm">
            <thead>
              <tr>
                <th class="text-left p-2">Site</th>
                <th class="text-left p-2">Notices</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($topSites as $row): ?>
                <tr class="docs-table-row">
                  <td class="p-2"><?php echo esc($row['site_name']); ?></td>
                  <td class="p-2"><?php echo (int)$row['cnt']; ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

