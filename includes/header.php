<?php
// includes/header.php
// NOTE: keep absolutely no whitespace before the opening PHP tag to avoid header issues.
if (!defined('SITE_NAME')) define('SITE_NAME', 'Defect Tracker');

// Try to detect current path for active link styling.
$currentPath = $_SERVER['REQUEST_URI'] ?? '';
function is_active($path, $currentPath) {
  // crude match: exact or starts with (for subpaths)
  if ($path === '/') return $currentPath === '/' || $currentPath === '';
  return (strpos($currentPath, $path) === 0);
}

// Check if a session is already started to safely show username
$loggedInUser = (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['user'])) ? $_SESSION['user'] : null;

// Optional: base URL if defined in config
$baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<link rel="icon" type="image/png" href="/assets/img/favicon.png">
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?php echo SITE_NAME; ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="/assets/css/admin.css?v=20250730f">
</head>
<body class="bg-gray-50">

  <!-- Top Nav -->
  <header class="sticky top-0 z-40 bg-white border-b shadow-sm">
    <div class="max-w-7xl mx-auto px-3 sm:px-4">
      <div class="h-14 flex items-center justify-between gap-3">
        <!-- Brand -->
        <a href="<?php echo $baseUrl ?: '/dashboard.php'; ?>" class="flex items-center gap-2 min-w-0">
          <img src="<?php echo $baseUrl; ?>/assets/img/mcgoff.png" alt="Logo" class="h-8 w-8 object-contain"/>
          <span class="font-semibold truncate"><?php echo SITE_NAME; ?></span>
        </a>

        <!-- Primary Nav -->
        <nav class="hidden md:flex items-center gap-1">
          <?php
            $links = [
              ['label' => 'Dashboard',          'href' => '/dashboard.php'],
              ['label' => 'New Clean‑Up Notice','href' => '/forms/clean-up/form.php'],
              ['label' => 'View Notices',       'href' => '/forms/clean-up/list.php'],
            ];
            foreach ($links as $ln):
              $active = is_active($ln['href'], $currentPath);
          ?>
            <a href="<?php echo $ln['href']; ?>"
               class="px-3 py-2 rounded text-sm <?php echo $active ? 'bg-blue-600 text-white' : 'text-gray-700 hover:bg-gray-100'; ?>">
              <?php echo $ln['label']; ?>
            </a>
          <?php endforeach; ?>
        </nav>

        <!-- User / Logout -->
        <div class="flex items-center gap-2">
          <?php if ($loggedInUser): ?>
            <span class="hidden sm:inline text-sm text-gray-600">Hi, <?php echo htmlspecialchars($loggedInUser, ENT_QUOTES, 'UTF-8'); ?></span>
          <?php endif; ?>
          <a href="/logout.php" class="px-3 py-2 rounded text-sm border hover:bg-gray-50">Logout</a>

          <!-- Mobile menu button (simple) -->
          <button id="menuBtn" class="md:hidden px-3 py-2 border rounded" aria-label="Menu">☰</button>
        </div>
      </div>
    </div>

    <!-- Mobile Nav -->
    <div id="mobileNav" class="md:hidden hidden border-t">
      <nav class="max-w-7xl mx-auto px-3 py-2 flex flex-col">
        <a href="/dashboard.php"
           class="px-3 py-2 rounded text-sm <?php echo is_active('/dashboard.php', $currentPath) ? 'bg-blue-600 text-white' : 'text-gray-700 hover:bg-gray-100'; ?>">
          Dashboard
        </a>
        <a href="/forms/clean-up/form.php"
           class="mt-1 px-3 py-2 rounded text-sm <?php echo is_active('/forms/clean-up/form.php', $currentPath) ? 'bg-blue-600 text-white' : 'text-gray-700 hover:bg-gray-100'; ?>">
          New Clean‑Up Notice
        </a>
        <a href="/forms/clean-up/list.php"
           class="mt-1 px-3 py-2 rounded text-sm <?php echo is_active('/forms/clean-up/list.php', $currentPath) ? 'bg-blue-600 text-white' : 'text-gray-700 hover:bg-gray-100'; ?>">
          View Notices
        </a>
      </nav>
    </div>
  </header>

  <!-- Page content starts after header -->

  <script>
    // Simple mobile menu toggle
    (function(){
      var btn = document.getElementById('menuBtn');
      var nav = document.getElementById('mobileNav');
      if (!btn || !nav) return;
      btn.addEventListener('click', function(){
        if (nav.classList.contains('hidden')) {
          nav.classList.remove('hidden');
        } else {
          nav.classList.add('hidden');
        }
      });
    })();
  </script>
