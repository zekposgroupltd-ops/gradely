# GRADELY: verification status (honest)

"Passed" means the test was executed in the build environment and its output shows it. Everything else is listed as not verified.

## Executed and passing (222 checks, `tests/run_all.sh`)
| Suite | Checks | Covers |
|---|---|---|
| Lint | all PHP files | syntax |
| `engine_test.php` | 30 | grade boundaries (incl. edge values), tie handling (competition 1,2,2,4 / dense 1,2,2,3), ordinals, integer-hundredths math, Rich Harvest sample (548/600 = 91.33%), incomplete subjects excluded, score audit history, lock while approved |
| `cards_test.php` | 38 | 5 uses then rejected, wrong term, wrong school, invalid/malformed PIN, unknown/unpublished student, **failures never consume a use**, first-student binding, revoked, expired, PIN with dashes, no plaintext PIN in DB, stats, **12 parallel processes racing for one card: exactly 5 win** (SQLite) |
| `tenant_isolation_db.php` | 13 | database refuses cross-school references |
| `smoke_auth.sh` | 31 | login, RBAC, CSRF, rate limit, headers, suspended users/schools |
| `e2e_http.py` | 91 | full workflow over HTTP: register school → academics → CSV import (bad rows skipped) → scores (over-max rejected) → compute → approve → publish → locks → cards → one-time PIN CSV → portal ×5 then 6th refused → wrong term/school/PIN → reload doesn't consume → display modes grades/positions/both on portal AND staff report → bulk report → analytics → cross-school URL tampering → revoke → suspend → CSV formula-injection guard → no PHP warnings logged |
| `e2e_browser.py` | 19 | XLSX import via browser form, manifest/maskable icons/service worker/offline page, iOS meta, **report card prints on exactly one A4 page**, no horizontal scroll on mobile (390px) |

Bugs found by these tests and fixed during the build include: analytics page reading the wrong variable (`$data` clash with the view renderer), suspended-school portal URL returning 200 instead of 404, and the PWA reloading the page on first install (which could wipe a half-typed login form; found as an intermittent test timeout).

## NOT verified (be aware)
- **MySQL/MariaDB.** All tests ran on SQLite using a converted copy of the schema. MySQL-specific behaviour (row locking with `SELECT … FOR UPDATE`, CHECK constraints on old versions, charset/collation, `SET` evaluation order) is untested. Run `tests/card_concurrency_mysql.php` on your database before issuing real cards.
- **The target host.** The host named in the brief ("Etio") could not be identified; the build assumes ordinary cPanel-style PHP + MySQL. Confirm PHP version/extensions (`pdo_mysql`, `gd`, `zip`) and that you can place files outside the web root.
- **Real devices.** PWA install prompt, iOS Add-to-Home-Screen and real phone rendering were not tested on physical devices (headless Chromium only; the install prompt itself cannot be triggered headlessly).
- **Load.** No load/performance testing beyond the 12-way race.
- **Bulk import of large files** (limits: 2 MB / 2000 rows) and **huge classes** for bulk report cards.

- **Docker / EthioDeploy route.** `Dockerfile`, `docker/entrypoint.sh` and `tools/install_schema.php` were written but never built or run (no Docker daemon or MySQL available). Only the schema-statement splitting was checked (27 statements = 26 tables + `SET NAMES`).

## Known limitations / design choices
- **PDF = browser Print → Save as PDF** with an A4 stylesheet. There is no server-side PDF engine.
- Portal print/download can be disabled per school (best-effort: a determined student can still screenshot).
- Plain PHP, no Laravel/Livewire/Tailwind (an earlier pasted spec mentioned them; none was built).
- PIN plaintext sits in the admin's server-side session until downloaded/discarded.
- Email/SMS delivery is not implemented; notifications are in-app only.
- Card binding default: a card locks to the first student who uses it (per-school setting).
