# Automated backups and real recovery signoff

## A. Entire Construction Suite — Plesk Backup Manager

**Recommended primary protection:** schedule a **daily** Plesk backup at the
subscription or account level, covering **web files AND databases** for
the Hub, Defects, Deliveries, Safety, Permits, Notices, Handover, Programme,
and Status. These applications store uploaded signatures, photographs and
PDFs outside their Git source snapshots; a database-only backup is not
enough to recover a site.

In Plesk: **Websites & Domains → Backup Manager → Schedule** (or,
for subscription access, **Subscriptions → Dashboard → Backup & Restore
→ Schedule**). Enable automatic backup, choose an off-peak time,
include **User files and databases**, use full backups at an appropriate
interval (e.g. weekly) plus daily incrementals when available, keep
at least 14–30 days of restore points, and enable error email alerts.
Configure **remote/off-site storage** so server failure does not also
destroy its backups. If multiple subscriptions are used, confirm each
subscription is covered. Treat Plesk's own scheduling and error email
as the source of truth; GitHub CI cannot verify it remotely.

**Check that Plesk includes the private file directories and runtime
uploads but that backup archives are not publicly served.**

## B. Independent Notices database dumps (optional additional protection)

GitHub includes a tested CLI-only database dumper:
`bin/backup-database.php`.

Use **Plesk → sitenotices.site → Scheduled Tasks → Add Task →
Run a PHP script** with script path:

`sitenotices.site/httpdocs/bin/backup-database.php`

Set **daily at 02:00**, Plesk/server local time, and send task
errors to the administrator. It reads the server-only
`includes/db.local.php` so there is NO need to type the key or password
again. Requires an executable `mariadb-dump`/`mysqldump` binary and
`proc_open`; it explicitly fails if unavailable. Dumps go to
`sitenotices.site/.site-backups/notices/` OUTSIDE `httpdocs` and
Git with directory permissions 0700, file permissions 0600.
No public backup link is generated. It maintains the latest 30 dumps,
with SHA-256 sidecars. Do not point the Git deployment path or a public
file browser into `.site-backups`.

Optional second Scheduled Task, **daily at 02:15**:

`sitenotices.site/httpdocs/bin/verify-backup.php`

The checker reads and decompresses the newest dump, checks the SHA-256,
and verifies SQL schema is present. **This is not a genuine production
restore test**; schedule Plesk Backup Manager too, particularly for
attachments and PDFs. Ensure any independently stored local dumps
don't recursively fill the Plesk Backup Manager archive.

## C. Recovery drills — do not touch live production tables

1. Confirm a successful, recent Plesk backup and a recent Notices
   `notices-*.sql.gz` if the optional dumper is enabled.
2. Recover into a separate **staging database and staging web root**.
   Never import over a live database just to test.
3. Compare source and recovered counts, representative records and
   statuses, full image/PDF/signature attachments, and account roles.
   Never paste PII or private keys into CI logs.
4. Verify the end-to-end lifecycle on staging: login; new notice;
   attach images; assign and issue; view; close; email/PDF; offline
   prepare/reconnect; retries and duplicate prevention.
5. Verify a denied or anonymous user cannot access other project data,
   including via direct detail URLs and Suite API filters.
6. Log date, recovery-point age, recovered counts and signoff person.
   Restore drills should be repeated monthly and after DB schema changes.

## Evidence from GitHub CI

`.github/workflows/backup-restore.yml` creates a **disposable**
MariaDB database with simulated notices, runs the same backup script,
verifies the dump and checksum, restores into a second disposable DB,
and checks the records. It has **no access to production data** and
therefore does not replace the Plesk restore drill.

Do not put backups, SQL dumps, private credentials or evidence documents
in Git repositories. Changing Git history is separate from the
operational backup requirements.
