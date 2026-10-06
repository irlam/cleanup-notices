# Reconnect Plesk after the clean Git history

The public GitHub `main` branch was replaced by a **new root commit**.
It retains application code but no longer tracks generated PDFs, photos,
signatures, or previous credential-bearing Git commits.

**IMPORTANT: stop automatic deployment until the runtime files are secured.**
Plesk documentation warns that deploying commits removing files/directories
can remove those published files *without warning*. This is why these steps
must be completed **before** deploying the cleaned repository.

## Required sequence in Plesk (site: sitenotices.site)

1. In **Git**, change the repository to **Manual deployment** while
   completing the steps below. Do not press Deploy yet.
2. In **Files**, navigate to `sitenotices.site/httpdocs`. Use File Manager
   to copy **both** `uploads/` and `pdfs/` into a private directory
   under `sitenotices.site/` but OUTSIDE `httpdocs`, such as
   `sitenotices.site/private-runtime-media/`. Check the files actually
   exist in the private destination; never rely only on a 'completed' banner.
   This is preservation of live evidence, **not** a Git backup.
3. Confirm private `httpdocs/includes/db.local.php` and
   `httpdocs/includes/suite.local.php` remain intact. Never expose their
   contents or copy either file into Git.
4. Use Plesk's **Remove Repository** to remove the old Git clone
   (which still holds prior credential-bearing Git objects locally).
   Plesk documentation states the published target directory remains
   in place. Re-add the same remote
   `https://github.com/irlam/cleanup-notices` with **main**, the
   original deployment path, and **Manual deployment**. Verify the
   path carefully; do not initialise a new empty document root.
   Merely pulling a rewritten branch can leave old objects in the local
   Git clone, even if it succeeds.
5. **Deploy now** after steps 1–4. Confirm private config still exists.
   Then copy preserved generated `uploads/` and `pdfs/` content
   back into the original `httpdocs` locations if Git deployment
   removed it. Preserve owner/permissions and check multiple old notices,
   attachments, signatures and downloadable PDFs.
6. Open the dashboard and notices list. Check the read-only Suite
   integrations are still returning authenticated live totals and
   anonymous API requests are HTTP 401.
7. Only after checks pass, consider enabling automatic deployment.
   Keep runtime `uploads/` and `pdfs/` outside Git forever.

## GitHub cleanup remaining

Old `security/private-notices-db-config` and
`security/private-notices-db-config-ready` branch tips were also reset
to the same clean root, so neither branch references earlier code.
The GitHub connection does not provide deleting branch references; delete
these two branch *names* in GitHub's **Branches** page when ready.

Even after a history rewrite, old pull requests, cached Git objects,
forks and previous clones may retain former content. GitHub Support may
need to remove sensitive cached references. **Rotate the previously
exposed DB password and any affected tokens now**; the rewrite does not
revoke copied secrets or documents.

No production database records were changed by rewriting GitHub history.
