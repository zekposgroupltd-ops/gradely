#!/usr/bin/env bash
# Runs every automated suite that can run without MySQL. usage: tests/run_all.sh [scratch-dir]
# Exit code 0 only if every suite reports "0 failed".
# Needs: php (pdo_sqlite, gd, zip), python3 + playwright + openpyxl (browser suite only; skipped if missing).
set -u
run() { out="$("$@" 2>&1)"; line="$(printf "%s" "$out" | tail -1)"; echo "$line"; printf "%s" "$line" | grep -q " 0 failed" || { rc=1; printf "%s\n" "$out" | grep "^FAIL" | head -10; }; }
ROOT="$(cd "$(dirname "$0")/.." && pwd)"; T="${1:-$(mktemp -d)}"; mkdir -p "$T"; rc=0
echo "== lint";            bad=0; for f in $(find "$ROOT/app" "$ROOT/public" "$ROOT/tests" -name '*.php'); do php -l "$f" >/dev/null 2>&1 || { php -l "$f"; bad=1; }; done; [ $bad = 0 ] && echo "all PHP files lint clean" || rc=1
echo "== env / DATABASE_URL"; run php "$ROOT/tests/env_test.php"
echo "== engine";          run php "$ROOT/tests/engine_test.php" "$T"
echo "== scratch cards";   run php "$ROOT/tests/cards_test.php" "$T"
echo "== tenant isolation (DB)"; run php "$ROOT/tests/tenant_isolation_db.php" "$T"
echo "== auth smoke";      run bash "$ROOT/tests/smoke_auth.sh" "$T/smoke"
echo "== HTTP workflow";   run python3 "$ROOT/tests/e2e_http.py" "$T"
if python3 -c "import playwright, openpyxl" 2>/dev/null; then echo "== browser (PWA, XLSX, A4, mobile)"; run python3 "$ROOT/tests/e2e_browser.py" "$T"; else echo "== browser suite SKIPPED (playwright/openpyxl missing)"; fi
echo; [ $rc = 0 ] && echo "ALL SUITES PASSED" || echo "SOME SUITES FAILED"; exit $rc
