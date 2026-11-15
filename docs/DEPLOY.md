# Deploying GRADELY (shared hosting / cPanel, or any PHP + MySQL host)

## Requirements
PHP 8.1+ with `pdo_mysql`, `gd`, `zip`, `mbstring`, `openssl`; MySQL 8.0.16+ or MariaDB 10.2+ (CHECK constraints are enforced from those versions; older servers accept the schema but ignore them); HTTPS.

## Steps
1. **Create the database.** In cPanel > MySQL Databases: create a database and a user, give the user ALL privileges.
2. **Import the schema.** phpMyAdmin > select the database > Import > `database/schema.sql`.
3. **Upload the files.**
   - Best: upload the whole folder *outside* the web root (e.g. `/home/USER/gradely`) and point the domain/subdomain document root at `gradely/public`.
   - If you cannot change the document root: put the app folder outside `public_html`, copy the *contents of `public/`* into `public_html/`, and set the environment variable `GRADELY_BASE=/home/USER/gradely` (in `public_html/.htaccess` add `SetEnv GRADELY_BASE /home/USER/gradely`). If you must install in a sub-folder, set `APP_BASE_PATH=/subfolder` in `.env`.
   - Never leave `app/`, `database/`, `storage/`, `.env` or `tests/` inside a web-accessible folder.
4. **Configure.** Copy `.env.example` to `.env` and set `APP_URL` (https), `APP_SECRET` (random, 32+ chars; **back it up**: changing it invalidates every issued card PIN), `DB_*`, `APP_TIMEZONE`, and a temporary `SETUP_TOKEN` (random string).
5. **Make `storage/` writable** (`storage/sessions`, `storage/logs`, `storage/uploads`), e.g. chmod 750/770 depending on the host.
6. **Create the first Super Admin.** Visit `https://your-domain/setup`, enter the `SETUP_TOKEN` value in the form together with the Super Admin name, email and password. Then **blank `SETUP_TOKEN` in `.env`**. The page returns 404 once a Super Admin exists.
7. **Turn on HTTPS enforcement.** Once your SSL certificate works, set `FORCE_HTTPS=1`. (Behind Cloudflare or another proxy also set `TRUST_PROXY=1`.) HTTPS is also required for PWA installation and the service worker.
8. **Smoke test:** sign in as Super Admin, register a school, sign in as that school's admin, and walk the setup checklist on the dashboard.

## Verify concurrency on YOUR database (recommended before issuing real cards)
Use a throw-away database (schema imported, one school + term + student `ADM002` with a published result):
`php tests/card_concurrency_mysql.php <school_id> <term_id> 30` : must print `PASS` (exactly 5 of 30 parallel requests succeed).

## Backups and upgrades
Back up the database and `.env` (and `storage/uploads`). To release an update, replace the files and bump `VERSION` in `public/sw.js` so installed apps refresh.

## PWA
Chrome/Edge/Android show an **Install** button (also in the header). iPhone/iPad: Safari > Share > **Add to Home Screen** (the app shows these instructions). Pages are never cached offline (results are private); only the app shell and an offline page are.

## Security notes
PINs are hashed; admins see a PIN only once at generation. The plaintext is held in the server-side session file until downloaded or discarded, so keep `storage/sessions` private and discard PINs after printing. Rate limits apply to portal checks (per IP, student, school) and logins.

---

# Deploying to GitHub + EthioDeploy (Docker route)

EthioDeploy builds from a GitHub repo and uses the `Dockerfile` in the repo root. This route is **untested**: the Dockerfile could not be built in the build environment (no Docker daemon), so expect to read the first build log and fix small things.

## 1. Put the code on GitHub (private repo)
```
cd gradely
git init && git add . && git commit -m "GRADELY"
git branch -M main
git remote add origin https://github.com/YOUR-USER/gradely.git
git push -u origin main
```
Check `git status` shows no `.env` (it is in `.gitignore`). Never commit real secrets.

## 2. Create the project on EthioDeploy
1. Connect the GitHub repo. It should detect the `Dockerfile` ("If your repo has its own Dockerfile, we use that").
2. Add the **MySQL 8** add-on (a project can have Postgres *or* MySQL; choose MySQL). Note its host, port, database, user and password.
3. Set these environment variables:

| Variable | Value |
|---|---|
| `APP_ENV` | `production` |
| `APP_URL` | your public https URL (no trailing slash) |
| `APP_SECRET` | random 32+ characters. **Back it up**: changing it invalidates every issued card |
| `APP_TIMEZONE` | `Africa/Addis_Ababa` (or your zone) |
| `DB_DRIVER` | `mysql` |
| `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` | from the MySQL add-on |
| `SETUP_TOKEN` | a random string, used once |
| `TRUST_PROXY` | `1` (the platform terminates HTTPS in front of the container) |
| `FORCE_HTTPS` | `1` once the https URL works |

4. Deploy. On first start the container creates the tables automatically (`tools/install_schema.php`; only runs when the database is empty; disable with `AUTO_INSTALL_SCHEMA=0`). The container listens on `$PORT` (default 80). If EthioDeploy asks for a port, use the one it injects or 80.
5. Open `https://your-url/setup`, enter `SETUP_TOKEN`, create the Super Admin, then **delete `SETUP_TOKEN`** from the variables and redeploy.

## 3. Things to know
- **Container files are usually not persistent.** Uploaded school logos and PHP session files live in `storage/` inside the container and can be lost on redeploy or restart (users get signed out; logos must be re-uploaded). Check whether EthioDeploy offers a persistent volume and mount it at `/var/www/html/storage`. All results, scores and cards live in MySQL, which persists.
- **Free plan sleeps** after 30 idle minutes (first request is slow). Use Pro for a live school portal on result day.
- Keep the Free plan's **512 MB database** in mind for large schools.
- Run `tests/card_concurrency_mysql.php` against a throw-away MySQL database before issuing real cards (see above).
