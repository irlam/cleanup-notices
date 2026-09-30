<?php if (!empty($loggedInUser)): ?>
<p id="offlineFeedback" role="status" class="docs-muted text-center"></p>
<div class="offline-toolbar"><a id="offlineBadge" href="/field.html">Opening offline storage…</a><button id="prepareOffline" type="button" class="btn-secondary">Prepare offline use</button><button id="syncNow" type="button" class="btn-secondary">Sync now</button></div>
<?php endif; ?>
<aside class="docs-install" aria-label="Install Site Documents">
  <button type="button" class="btn-secondary" id="installApp" hidden>Install Site Documents</button>
  <details><summary>Add to your phone</summary><p>On iPhone or iPad, open this site in Safari, tap Share and choose Add to Home Screen. On Android, use the browser menu and choose Install app or Add to Home screen.</p><p>Prepare Offline Workspace while connected, then create and queue notices without signal. Uploads and email delivery complete when you reconnect and are signed in.</p></details>
</aside>
<footer class="docs-footer">Site Documents <span>·</span> Clean-up notice management</footer>
</body>
</html>
