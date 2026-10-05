# Private database configuration migration

**Safety gate: do not merge or deploy this security branch until the server-only prerequisite is completed.**

The original tracked includes/db.php contained live database credentials, including in Git history.
Treat that password as compromised and rotate it. Never post the credential in screenshots or logs.

1. First deploy the current main branch, which includes the CLI-only
   bin/prepare-private-db.php script while the old db.php still works.
   In Plesk PHP CLI / Scheduled Tasks, run:
      php httpdocs/bin/prepare-private-db.php
   The script copies existing settings to the untracked db.local.php with
   restrictive file permissions without displaying or retyping credentials.
   The source script rejects HTTP requests. Verify the file exists in Plesk.
   If PHP CLI is unavailable, copy db.local.example.php to db.local.php
   manually and enter the current settings privately in Plesk.
2. Ensure db.local.php is private, untracked and the pass value is not blank.
   Keep a database backup, and disable automatic deployment during migration.
3. Deploy this security branch, then test login, dashboard, site notices and offline preparation.
4. Rotate the database user's password in Plesk, update db.local.php, and verify again.
5. Merge the branch to main only when production works.
6. Plan a controlled history rewrite because current-file changes do not remove old secrets from Git history.

NOTICES_DB_HOST, NOTICES_DB_PORT, NOTICES_DB_NAME, NOTICES_DB_USER and
NOTICES_DB_PASS environment variables may be used instead of db.local.php.
Never commit actual settings.
