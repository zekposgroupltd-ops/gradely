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
