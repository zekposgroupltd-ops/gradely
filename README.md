# GRADELY: Academic Results. Simplified.
Powered by Techpreneur. A multi-school results platform with three areas:
Super Admin (`/super`), School Admin / staff (`/school`) and a public, mobile-friendly Student Result Portal (`/`), installable as a PWA.

Plain PHP 8.1+ (no Composer, no framework), MySQL 5.7+/MariaDB 10.3+ in production. See `docs/DEPLOY.md` to install and `docs/CHECKLIST.md` for what has and has not been tested.

## Layout
- `public/` web root (front controller, assets, PWA files). Point the domain here if you can.
- `app/` Core, Controllers, Services (ResultEngine, ScratchCardService, ReportService, importers), Views.
- `database/schema.sql` the full schema (import once).
- `storage/` sessions, logs, uploaded logos. Must be writable and must not be web-accessible.
- `tests/` automated suites. `tests/run_all.sh` runs everything that can run without MySQL.

## Roles
Super Admin: schools, licences, suspend/activate, platform logs, card inventory across schools, notifications, settings.
School Admin: everything in one school. Teacher: per-permission (students, scores for assigned class+subject, results view/approve/publish, cards).

## Key behaviours
- A scratch card is locked to one school and one term, allows 5 successful checks, and only a successful check consumes a use (one guarded atomic UPDATE plus usage row in one transaction). PINs are stored only as HMAC-SHA256 hashes; the plaintext is shown/exported once.
- Result display setting (grades / positions / both) is enforced on the server for the portal, report cards and print.
- Results move draft → approved → published. Scores and recompute are locked while approved/published.
- Every tenant table carries `school_id` and composite foreign keys so the database itself refuses cross-school references.


## Production-safe feature upgrade (17 Nov 2026)

This release adds school logo presentation, student ID prefixes/auto IDs, a limited teacher role, PIN purchase requests and approval, class-scoped PIN batches, PDF print entitlement checks, and a refreshed public result landing page. It is an additive upgrade for an existing deployment.

**Before deploying:** take a database backup and retain your current `.env`. Do not run `tools/install_schema.php` on an existing database. That script is for an empty database only.

After the new source files are deployed, run:

```sh
php tools/upgrade_2026_11_17.php
```

The upgrade script only adds the `admission_prefix` column, PIN request table and batch metadata/indexes/foreign keys, adds a default platform PIN price, and restricts teacher accounts to score entry. It does not drop or truncate data, recreate accounts, reset passwords, or replace the existing database. Back up first regardless.

Set the result-checking PIN price under **Super Admin → Settings**. Schools then request PINs for a class or the whole school; the platform administrator verifies payment and approves the request; the school generates the approved PIN batch. Admin-side report-sheet printing is enabled only when the school has fulfilled an approved PIN request with enough valid PINs for the class or whole school. Old pre-upgrade card batches remain usable for result checking, but do not count as a paid allocation for admin PDF printing.

Schools can upload their own logo under **School Profile → School logo**. They can set a student ID prefix (for example `SGW`); leaving a new student's ID blank generates the next ID (`SGW0001`, `SGW0002`, etc.). Teachers are limited to five accounts per school and can only upload scores for assigned subjects/classes.
