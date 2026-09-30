# Site Documents offline workflow

Deploy the complete commit using the existing hosting Git deployment. PHP/MySQL remain the server runtime; Node is only used for development checks and CSS generation.

1. Sign in on the phone while connected, and choose **Offline → Prepare for offline use**.
2. Wait for the ready message. Preparation stores the editor and PDF tools and downloads the latest 50 notice snapshots with their available PDFs, photos and signatures. Check the file download dates; any failures are reported and can be retried with Refresh recent notices.
3. Without signal, reopen the PWA or Offline Workspace. Create notices, attach photos, annotate and sign. Drafts autosave, with an explicit Save draft on device button. Submit stores the complete request in IndexedDB before attempting any upload.
4. Existing downloaded notices can be viewed offline and queued for close-out. Draft PDFs are generated locally and clearly labelled as awaiting sync. Official PDFs are created on the server and downloaded after confirmed submission.
5. When connected again, keep the app open. It retries automatically on reconnect, app foreground and every 30 seconds. Background Sync is also registered on browsers that support it; iOS or a suspended/closed app may require reopening. If the session expired, sign in as the original author.

## Server requirements

HTTPS, PHP 8+, PDO MySQL, the existing image/PDF dependencies, and InnoDB for `cleanup_notices` and `cleanup_photos` are required. The authenticated setup endpoint creates `notice_submission_receipts` automatically; the database account needs CREATE TABLE permission. No command-line migration is required on standard installations.

If preparation reports that setup is unavailable, check the PHP error log. On older installations an administrator can inspect table engines in phpMyAdmin and convert the two notice tables to InnoDB after taking a backup. The app deliberately refuses transactional sync with nontransactional tables. Set PHP upload_max_filesize, post_max_size and max_file_uploads high enough for the photos staff use. Partial uploads are rejected and retained on the device, rather than accepted with missing attachments.

The receipt table has a unique `(user_name, client_id)` key. Each durable submission carries its UUID, owner and a fresh session CSRF token. Receipt, notice, attachment rows and the generated PDF path commit together. Failed preparation rolls back and removes its temporary files. Retrying a committed request returns its receipt without creating another notice or resending email. Close-outs use the same receipts and preserve the original close-out timestamp.

SMTP delivery is claimed once after commit. Failed or uncertain delivery is shown separately from successful notice upload. SMTP cannot provide an atomic database/email transaction; uncertain delivery is never automatically resent. An administrator should review it before attempting another send. The session lock is released while SMTP runs, and the device can refresh the receipt's delivery status.

## Device data

The service worker caches only a generic editor and public assets. Server-rendered account pages are never cached. Notices, attachment Blobs, snapshots and PDFs live in IndexedDB, scoped to the active username. Signing out locks the workspace without deleting unsynced work; signing in as a different user cannot view or submit the previous account's queue. Multiple sync agents coordinate through a renewable IndexedDB lease, and the server receipt is the final duplicate guard.

Copies remain on the device until removed explicitly. Removing a local copy does not delete a server notice. Device/browser storage can be cleared by the user or operating system; check save confirmations and sync important work before clearing app data. Requesting persistent storage helps where the browser supports it. The workspace reports quota failures and retains the live form.

## Development checks

Run `npm install`, `npm test`, and `npx playwright install chromium` followed by `npm run test:browser`. To use an existing Chromium binary, set `CHROMIUM_PATH`.

The browser check runs against an isolated HTTP test server, not production PHP/MySQL or SMTP. It verifies offline reopening, draft restoration with attachments, queuing, lost acknowledgement recovery, cached PDF delivery, local PDF generation, offline close-out, mobile layout and account switching. The queue tests cover cross-tab leasing, account mismatch, failed PDF download and permanent validation errors. Perform a real test notice after deployment to verify hosting database privileges, image processing, PDF generation and SMTP.

After editing the live form, run `npm run build:offline` to update the static offline editor, then `npm run build:css`. Keep the offline-store, notice-form and offline-app scripts shared between both editors. jsPDF is bundled locally under `assets/vendor` so PDF generation needs no CDN connection. When publishing later offline-asset changes, bump the service worker shell cache version.
