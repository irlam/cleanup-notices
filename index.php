<?php
session_start();
if (isset($_SESSION['user'])) { header('Location: dashboard.php'); exit; }
require_once __DIR__ . '/includes/header.php';
?>
<main class="docs-login">
  <div class="docs-login-card">
    <section class="docs-login-hero">
      <span class="docs-eyebrow">Site document control</span>
      <div class="docs-login-brand"><img src="/assets/brand/site-documents-logo.png" alt="" width="88" height="88"><span>Site Documents<small>Clean-up notice management</small></span></div>
      <h1>Cleaner sites.<br>Clearer records.</h1>
      <p>Create, issue and track clean-up notices from wherever you are on site.</p>
      <div class="docs-login-features"><span>Photos &amp; signatures</span><span>PDF notices</span><span>Track &amp; close out</span></div>
      <a href="/how-it-works.html">See how it works →</a>
    </section>
    <section class="docs-login-form">
      <h2>Sign in</h2>
      <p class="docs-muted">Use your site credentials to continue.</p>
      <?php if (isset($_GET['error'])): ?>
        <div class="docs-alert" role="alert">Invalid username or password. Please try again.</div>
      <?php endif; ?>
      <form action="/includes/auth.php" method="post" class="space-y-4">
        <div><label for="username">Username</label><input type="text" id="username" name="username" autocomplete="username" required></div>
        <div><label for="password">Password</label><input type="password" id="password" name="password" autocomplete="current-password" required></div>
        <button type="submit" class="btn-primary docs-login-submit">Sign in →</button>
      </form>
      <p class="docs-login-help">Didn't receive your email? <a href="/resend_confirm.php">Resend confirmation</a></p>
      <p class="docs-muted">Need access? Contact your site administrator.</p>
    </section>
  </div>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
