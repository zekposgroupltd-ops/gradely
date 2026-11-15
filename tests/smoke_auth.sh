#!/usr/bin/env bash
# End-to-end authentication / authorisation smoke test against the PHP built-in server.
# Uses a SQLite copy of the schema (see tests/tools/make_test_db.php). usage: tests/smoke_auth.sh <scratch-dir>
set -u
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
TMP="${1:?scratch dir required}"; mkdir -p "$TMP"
DB="$TMP/smoke.sqlite"; JAR="$TMP/jar.txt"; PORT="${PORT:-8099}"; BASE="http://127.0.0.1:$PORT"

php "$ROOT/tests/tools/make_test_db.php" "$DB" >/dev/null || { echo "could not build test db"; exit 1; }
export DB_DRIVER=sqlite DB_SQLITE_PATH="$DB" APP_ENV=development
php -S "127.0.0.1:$PORT" -t "$ROOT/public" "$ROOT/public/index.php" >"$TMP/server.log" 2>&1 &
SRV=$!
trap 'kill $SRV 2>/dev/null' EXIT
sleep 1

pass=0; fail=0
ok()  { pass=$((pass+1)); echo "PASS  $1"; }
bad() { fail=$((fail+1)); echo "FAIL  $1"; }
check() { if eval "$2"; then ok "$1"; else bad "$1  [$2]"; fi; }

fresh() { rm -f "$JAR"; }
token() { curl -s -c "$JAR" -b "$JAR" "$BASE/login" | grep -o 'name="_csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"$//'; }
get()   { curl -s -o "$TMP/body.html" -w '%{http_code}' -c "$JAR" -b "$JAR" "$BASE$1"; }
do_login() { local t; t=$(token); curl -s -o /dev/null -w '%{http_code} %{redirect_url}' -c "$JAR" -b "$JAR" -d "_csrf=$t" --data-urlencode "email=$1" --data-urlencode "password=$2" "$BASE/login"; }
body_has() { grep -q -- "$1" "$TMP/body.html"; }

# --- public pages & headers
fresh
check "GET /login is 200" '[ "$(get /login)" = "200" ]'
check "login page shows tagline" 'body_has "Academic Results. Simplified."'
check "CSP header present" 'curl -sI "$BASE/login" | grep -qi "^content-security-policy:"'
check "X-Frame-Options present" 'curl -sI "$BASE/login" | grep -qi "^x-frame-options: DENY"'
check "session cookie is HttpOnly + SameSite=Lax" 'curl -sI "$BASE/login" | grep -i "^set-cookie: GRADELYSESS" | grep -qi "httponly" && curl -sI "$BASE/login" | grep -i "^set-cookie: GRADELYSESS" | grep -qi "samesite=lax"'
check "GET /setup is 404 once a super admin exists" '[ "$(get /setup)" = "404" ]'
check "unknown route is 404" '[ "$(get /nope)" = "404" ]'
check "logo asset is served" '[ "$(curl -s -o /dev/null -w "%{http_code}" "$BASE/assets/img/gradely-logo.png")" = "200" ]'

# --- CSRF
fresh; token >/dev/null
check "POST /login without CSRF token -> 419" '[ "$(curl -s -o /dev/null -w "%{http_code}" -c "$JAR" -b "$JAR" -d "email=a&password=b" "$BASE/login")" = "419" ]'

# --- bad credentials
fresh
r=$(do_login "a-admin@test.local" "wrong-password"); get /login >/dev/null
check "wrong password redirects to /login" 'echo "$r" | grep -q "^302 .*/login$"'
check "wrong password shows generic error" 'body_has "Invalid email or password."'
fresh
do_login "ghost@test.local" "whatever-123" >/dev/null; get /login >/dev/null
check "unknown email shows the SAME generic error (no enumeration)" 'body_has "Invalid email or password."'

# --- unauthenticated access
fresh
check "GET /school unauthenticated -> redirect" '[ "$(get /school)" = "302" ]'
check "GET /super unauthenticated -> redirect" '[ "$(get /super)" = "302" ]'

# --- super admin
fresh
r=$(do_login "super@test.local" "SuperPass#12345")
check "super admin login redirects to /super" 'echo "$r" | grep -q "^302 .*/super$"'
check "super dashboard renders" '[ "$(get /super)" = "200" ] && body_has "Platform overview"'
check "super dashboard counts the 3 seeded schools" 'grep -A1 "Registered schools" "$TMP/body.html" | grep -q "stat-value\">3<"'
check "super admin is refused on /school (403)" '[ "$(get /school)" = "403" ]'
get /super >/dev/null
t=$(grep -o 'name="_csrf" value="[^"]*"' "$TMP/body.html" | head -1 | sed 's/.*value="//;s/"$//')
r=$(curl -s -o /dev/null -w '%{http_code} %{redirect_url}' -c "$JAR" -b "$JAR" -d "_csrf=$t" "$BASE/logout")
check "logout redirects to /login" 'echo "$r" | grep -q "^302 .*/login$"'
check "after logout /super needs sign-in again" '[ "$(get /super)" = "302" ]'

# --- school admin A: sees own school only; blocked from platform area
fresh
r=$(do_login "a-admin@test.local" "AdminPass#12345")
check "school admin login redirects to /school" 'echo "$r" | grep -q "^302 .*/school$"'
check "school dashboard shows own school" '[ "$(get /school)" = "200" ] && body_has "Alpha Academy"'
check "school dashboard does NOT leak other schools" '! body_has "Beta College" && ! body_has "Gamma School"'
check "school admin is refused on /super (403)" '[ "$(get /super)" = "403" ]'
fresh; token >/dev/null
before=$(grep GRADELYSESS "$JAR" | awk '{print $7}')
do_login "a-admin@test.local" "AdminPass#12345" >/dev/null
after=$(grep GRADELYSESS "$JAR" | awk '{print $7}')
check "session id is regenerated at login (fixation defence)" '[ -n "$before" ] && [ -n "$after" ] && [ "$before" != "$after" ]'

# --- teacher
fresh
r=$(do_login "a-teacher@test.local" "TeacherPass#12345")
check "teacher can reach the school dashboard" '[ "$(get /school)" = "200" ] && body_has "Alpha Academy"'
check "teacher is refused on /super (403)" '[ "$(get /super)" = "403" ]'

# --- school B admin sees B, not A
fresh
do_login "b-admin@test.local" "AdminPass#12345" >/dev/null
check "school B admin sees Beta College only" '[ "$(get /school)" = "200" ] && body_has "Beta College" && ! body_has "Alpha Academy"'

# --- suspended school
fresh
r=$(do_login "c-admin@test.local" "AdminPass#12345"); get /login >/dev/null
check "suspended school admin cannot sign in" 'echo "$r" | grep -q "^302 .*/login$" && body_has "not active"'
check "suspended school admin has no session access" '[ "$(get /school)" = "302" ]'

# --- brute-force throttling (5 failures per email per 15 min)
fresh
for i in 1 2 3 4 5; do do_login "nobody@test.local" "bad-password-$i" >/dev/null; done
do_login "nobody@test.local" "bad-password-6" >/dev/null; get /login >/dev/null
check "6th attempt is throttled" 'body_has "Too many sign-in attempts"'

echo
echo "RESULT: $pass passed, $fail failed"
[ "$fail" -eq 0 ]
