# Construction Suite live integration

Site Notices provides two **read-only**, shared-key API endpoints:

- /api/suite-summary.php (metrics: total, open_count, closed_count, overdue, pending_closeout, created_today)
- /api/suite-references.php (existing notice site names for project mapping)

## Configuration

1. In the private Plesk file manager create httpdocs/includes/suite.local.php
   using includes/suite.local.example.php as a template.
2. Set CONSTRUCTION_SUITE_API_KEY to match the private
   SUITE_INTEGRATION_KEY in suite.defecttracker.uk/httpdocs/.env.
   The shared secret must be at least 32 characters. Never put it in Git.
3. Deploy the latest cleanup-notices main commit and test the endpoints.
   Unauthenticated requests should return 401. Unconfigured returns 503.
4. Open Construction Suite -> Admin -> Project modules to choose the site
   or All data in this module. The KPIs then populate in Live Site Snapshot.

Before production use, complete the separate security migration described
in docs/PRIVATE_DB_MIGRATION.md (draft PR) to remove the historically
tracked database credentials. Rotation of the exposed password is essential.
