<?php
if (!defined('SITE_NAME')) define('SITE_NAME', 'Site Documents');
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$loggedInUser = session_status() === PHP_SESSION_ACTIVE ? ($_SESSION['user'] ?? null) : null;
$links = [['Dashboard', '/dashboard.php'], ['New Notice', '/forms/clean-up/form.php'], ['View Notices', '/forms/clean-up/list.php']];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#0b1220">
  <title><?= htmlspecialchars(SITE_NAME, ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="icon" type="image/png" href="/assets/img/favicon.png">
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="/assets/css/admin.css?v=20260930">
</head>
<body class="docs-app<?= $loggedInUser ? '' : ' docs-public' ?>">
<header class="docs-nav">
  <div class="docs-nav-inner">
    <a href="<?= $loggedInUser ? '/dashboard.php' : '/index.php' ?>" class="docs-brand">
      <span class="docs-brand-icon" aria-hidden="true">▤</span>
      <span><?= htmlspecialchars(SITE_NAME, ENT_QUOTES, 'UTF-8') ?><small>Clean-up notice management</small></span>
    </a>
    <nav class="docs-desktop" aria-label="Main navigation">
      <?php if ($loggedInUser): foreach ($links as [$label, $href]): ?>
        <a href="<?= $href ?>" class="docs-nav-link<?= $currentPath === $href ? ' active' : '' ?>" <?= $currentPath === $href ? 'aria-current="page"' : '' ?>><?= $label ?></a>
      <?php endforeach; else: ?>
        <a href="/how-it-works.html" class="docs-nav-link">How it works</a>
      <?php endif; ?>
    </nav>
    <div class="docs-account">
      <?php if ($loggedInUser): ?>
        <span class="docs-username"><?= htmlspecialchars((string)$loggedInUser, ENT_QUOTES, 'UTF-8') ?></span>
        <a class="btn-secondary" href="/logout.php">Logout</a>
        <button type="button" id="menuBtn" class="docs-menu" aria-label="Open navigation" aria-controls="mobileNav" aria-expanded="false">☰</button>
      <?php else: ?>
        <a href="/index.php" class="btn-secondary">Sign in</a>
      <?php endif; ?>
    </div>
  </div>
  <?php if ($loggedInUser): ?>
  <nav id="mobileNav" class="docs-mobile" aria-label="Mobile navigation" hidden>
    <?php foreach ($links as [$label, $href]): ?>
      <a href="<?= $href ?>" class="docs-nav-link<?= $currentPath === $href ? ' active' : '' ?>" <?= $currentPath === $href ? 'aria-current="page"' : '' ?>><?= $label ?></a>
    <?php endforeach; ?>
  </nav>
  <?php endif; ?>
</header>
<script>
(() => {
  const btn = document.getElementById('menuBtn'), nav = document.getElementById('mobileNav');
  if (!btn || !nav) return;
  function close() { nav.hidden = true; btn.setAttribute('aria-expanded', 'false'); }
  btn.addEventListener('click', () => { nav.hidden = !nav.hidden; btn.setAttribute('aria-expanded', String(!nav.hidden)); });
  document.addEventListener('keydown', e => { if (e.key === 'Escape') { close(); btn.focus(); } });
  window.addEventListener('resize', () => { if (window.innerWidth >= 900) close(); });
})();
</script>
