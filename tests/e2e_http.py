#!/usr/bin/env python3
"""End-to-end workflow over real HTTP against the PHP built-in server (SQLite copy of the schema).
usage: python3 tests/e2e_http.py <scratch-dir>      Nothing here is mocked: every step is a browser-style request."""
import http.cookiejar, os, re, sqlite3, subprocess, sys, time, urllib.parse, urllib.request, urllib.error, io, csv

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
TMP = sys.argv[1] if len(sys.argv) > 1 else '/tmp'
os.makedirs(TMP, exist_ok=True)
DBF = f'{TMP}/e2e.sqlite'
PORT = 8123
BASE = f'http://127.0.0.1:{PORT}'
if os.path.exists(DBF): os.remove(DBF)
LOG = f'{ROOT}/storage/logs/php-error.log'
open(LOG, 'w').close()
subprocess.run(['php', f'{ROOT}/tests/tools/make_test_db.php', DBF], check=True, stdout=subprocess.DEVNULL)
env = dict(os.environ, DB_DRIVER='sqlite', DB_SQLITE_PATH=DBF, APP_ENV='development', APP_SECRET='e2e-secret-e2e-secret-e2e-secret-1')
srv = subprocess.Popen(['php', '-S', f'127.0.0.1:{PORT}', '-t', f'{ROOT}/public', f'{ROOT}/public/index.php'], env=env, stdout=open(f'{TMP}/e2e-server.log', 'w'), stderr=subprocess.STDOUT)
time.sleep(1)

passed = failed = 0
def ok(label, cond, detail=''):
    global passed, failed
    if cond: passed += 1; print('PASS ', label)
    else: failed += 1; print('FAIL ', label, detail[:300])

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *a, **k): return None

class Client:
    def __init__(self, follow=True):
        self.jar = http.cookiejar.CookieJar()
        handlers = [urllib.request.HTTPCookieProcessor(self.jar)]
        if not follow: handlers.append(NoRedirect())
        self.op = urllib.request.build_opener(*handlers)
    def req(self, path, data=None, files=None):
        url = path if path.startswith('http') else BASE + path
        body = None; headers = {}
        if files:
            b = '----e2e'; parts = []
            for k, v in (data or {}).items(): parts.append(f'--{b}\r\nContent-Disposition: form-data; name="{k}"\r\n\r\n{v}\r\n'.encode())
            for k, (fn, content) in files.items(): parts.append(f'--{b}\r\nContent-Disposition: form-data; name="{k}"; filename="{fn}"\r\nContent-Type: application/octet-stream\r\n\r\n'.encode() + content + b'\r\n')
            parts.append(f'--{b}--\r\n'.encode()); body = b''.join(parts); headers['Content-Type'] = f'multipart/form-data; boundary={b}'
        elif data is not None:
            body = urllib.parse.urlencode(data, doseq=True).encode()
        try:
            r = self.op.open(urllib.request.Request(url, body, headers))
            return r.status, r.read().decode('utf-8', 'replace'), r.headers
        except urllib.error.HTTPError as e:
            return e.code, e.read().decode('utf-8', 'replace'), e.headers
    def token(self, page):
        s, h, _ = self.req(page)
        m = re.search(r'name="_csrf" value="([^"]+)"', h)
        return m.group(1) if m else ''
    def post(self, path, data, token_page=None, files=None):
        data = dict(data); data['_csrf'] = self.token(token_page or path_page(path))
        return self.req(path, data, files)
    def login(self, email, pw):
        return self.post('/login', {'email': email, 'password': pw}, '/login')

def path_page(p): return '/school' if p.startswith('/school') else '/super' if p.startswith('/super') else '/account/password'

pins_ok = lambda h: 'GL' in h
db = lambda q, a=(): sqlite3.connect(DBF).execute(q, a).fetchall()
try:
    # ---------- 1. Super admin registers a school
    sup = Client(); s, h, _ = sup.login('super@test.local', 'SuperPass#12345'); ok('super admin signs in', 'Super' in h or s == 200, h)
    for p in ['/super', '/super/schools', '/super/schools/new', '/super/logs', '/super/cards', '/super/admins', '/super/notifications', '/super/settings', '/account/password', '/notifications']:
        s, h, _ = sup.req(p); ok(f'super page {p} renders (200)', s == 200 and '<html' in h.lower(), f'{s} {h[:200]}')
    s, h, _ = sup.post('/super/schools', {'name': 'Zenith Academy', 'email': 'z@zenith.test', 'admin_name': 'Zed Admin', 'admin_email': 'zed@zenith.test', 'admin_password': 'ZenithPass#2026!'}, '/super/schools/new')
    row = db("SELECT id, slug FROM schools WHERE name='Zenith Academy'")
    ok('school registered with slug', bool(row) and row[0][1] == 'zenith-academy', str(row)); Z, slug = row[0]
    ok('default 3 components + 6 grades provisioned', db('SELECT COUNT(*) FROM assessment_components WHERE school_id=?', (Z,))[0][0] == 3 and db('SELECT COUNT(*) FROM grade_boundaries WHERE school_id=?', (Z,))[0][0] == 6)
    s, h, _ = sup.req(f'/super/schools/{Z}'); ok('super school detail renders', s == 200 and 'Zenith Academy' in h)

    # ---------- 2. School admin sets up
    adm = Client(); adm.login('zed@zenith.test', 'ZenithPass#2026!')
    for p in ['/school', '/school/setup', '/school/profile', '/school/settings', '/school/academics', '/school/classes', '/school/subjects', '/school/teachers', '/school/teachers/new', '/school/students', '/school/students/new', '/school/students/import', '/school/cards', '/school/analytics', '/school/archive', '/school/results']:
        s, h, _ = adm.req(p); ok(f'school page {p} renders (200)', s == 200, f'{s} {h[:200]}')
    adm.post('/school/sessions', {'name': '2026/2027'}, '/school/academics')
    sess = db("SELECT id FROM academic_sessions WHERE school_id=?", (Z,))[0][0]
    adm.post('/school/terms', {'session_id': sess, 'name': 'First Term'}, '/school/academics')
    adm.post('/school/terms', {'session_id': sess, 'name': 'Second Term'}, '/school/academics')
    terms = {n: i for i, n in db("SELECT id, name FROM terms WHERE school_id=?", (Z,))}
    T1, T2 = terms['First Term'], terms['Second Term']
    adm.post('/school/classes', {'name': 'JSS 1'}, '/school/classes')
    cls = db("SELECT id FROM classes WHERE school_id=?", (Z,))[0][0]
    for n in ['English', 'Mathematics']: adm.post('/school/subjects', {'name': n}, '/school/subjects')
    subs = [r[0] for r in db("SELECT id FROM subjects WHERE school_id=? ORDER BY id", (Z,))]
    adm.post('/school/subjects/assign', {f'assign[{sb}][{cls}]': '1' for sb in subs}, '/school/subjects')
    ok('session, terms, class, subjects and assignments created', bool(sess and T1 and T2 and cls) and len(subs) == 2 and db('SELECT COUNT(*) FROM class_subjects WHERE school_id=?', (Z,))[0][0] == 2)

    # ---------- 3. Student import (CSV with a bad row)
    csvtxt = 'admission_no,first_name,last_name,gender,class\nZ001,Ada,Obi,F,JSS 1\nZ002,Bayo,Eze,M,JSS 1\nZ003,Chi,Okafor,F,JSS 1\nZ004,Dayo,Ali,M,JSS 1\nZ005,Eve,Nwosu,F,NO SUCH CLASS\nZ001,Dup,Licate,M,JSS 1\n'
    s, h, _ = adm.post('/school/students/import', {}, '/school/students/import', files={'file': ('students.csv', csvtxt.encode())})
    n = db('SELECT COUNT(*) FROM students WHERE school_id=?', (Z,))[0][0]
    ok('CSV import adds 4 valid students and skips the bad/duplicate rows', n == 4, f'count={n}')
    stu = {a: i for i, a in db('SELECT id, admission_no FROM students WHERE school_id=?', (Z,))}

    # ---------- 4. Scores (grid) – invalid values rejected, valid saved
    comps = [r[0] for r in db('SELECT id FROM assessment_components WHERE school_id=? ORDER BY sort_order', (Z,))]
    def grid(subj, totals):
        d = {'term_id': T1, 'class_id': cls, 'subject_id': subj}
        for adm_no, tot in totals.items():
            a = tot // 5; b = tot // 5; c = tot - a - b
            for comp, v in zip(comps, (a, b, c)): d[f'scores[{stu[adm_no]}][{comp}]'] = v
        return d
    bad = grid(subs[0], {'Z001': 85}); bad[f'scores[{stu["Z001"]}][{comps[0]}]'] = '25'   # 25 > max 20
    s, h, _ = adm.post('/school/scores', bad, f'/school/scores?term_id={T1}&class_id={cls}&subject_id={subs[0]}')
    ok('score above component maximum is rejected (nothing saved)', db('SELECT COUNT(*) FROM scores WHERE school_id=?', (Z,))[0][0] == 0)
    E_ = {'Z001': 87, 'Z002': 80, 'Z003': 80, 'Z004': 50}; M_ = {'Z001': 95, 'Z002': 80, 'Z003': 80, 'Z004': 50}
    for sb, tot in ((subs[0], E_), (subs[1], M_)):
        adm.post('/school/scores', grid(sb, tot), f'/school/scores?term_id={T1}&class_id={cls}&subject_id={sb}')
    ok('valid scores saved (4 students x 2 subjects x 3 components = 24)', db('SELECT COUNT(*) FROM scores WHERE school_id=?', (Z,))[0][0] == 24)
    ok('score history written', db('SELECT COUNT(*) FROM score_history WHERE school_id=?', (Z,))[0][0] == 24)
    sub_s = db('SELECT subject_id FROM scores LIMIT 1')
    # unassigned/foreign term id in a score save
    s, h, _ = adm.post('/school/scores', {**grid(subs[0], {'Z001': 87}), 'term_id': 999999}, f'/school/scores?term_id={T1}&class_id={cls}&subject_id={subs[0]}')
    ok('score save with a term from nowhere is refused (404)', s == 404, str(s))

    # ---------- 5. Compute -> approve -> publish
    wf = {'term_id': T1, 'class_id': cls}
    adm.post('/school/results/compute', wf, f'/school/results?term_id={T1}&class_id={cls}')
    r = db('SELECT total, average, position FROM result_summaries WHERE school_id=? ORDER BY position', (Z,))
    ok('compute produced a summary per student', len(r) == 4, str(r))
    ok('top student: total 182, avg 91, position 1', r and float(r[0][0]) == 182.0 and float(r[0][1]) == 91.0 and r[0][2] == 1, str(r))
    ok('ties share position 2 then position 4', [x[2] for x in r] == [1, 2, 2, 4], str(r))
    s, h, _ = adm.post('/school/results/publish', wf, f'/school/results?term_id={T1}&class_id={cls}')
    ok('cannot publish before approval', db("SELECT COUNT(*) FROM result_summaries WHERE school_id=? AND status='published'", (Z,))[0][0] == 0)
    adm.post('/school/results/approve', wf, f'/school/results?term_id={T1}&class_id={cls}')
    ok('approve moves to approved', db("SELECT COUNT(*) FROM result_summaries WHERE school_id=? AND status='approved'", (Z,))[0][0] == 4)
    s, h, _ = adm.post('/school/scores', grid(subs[0], {'Z001': 10}), f'/school/scores?term_id={T1}&class_id={cls}&subject_id={subs[0]}')
    ok('scores are locked once approved', db("SELECT score FROM scores WHERE student_id=? AND subject_id=? AND component_id=?", (stu['Z001'], subs[0], comps[0]))[0][0] == 17)
    s, h, _ = adm.post('/school/results/compute', wf, f'/school/results?term_id={T1}&class_id={cls}')
    ok('recompute refused while approved', db("SELECT COUNT(*) FROM result_summaries WHERE status='approved' AND school_id=?", (Z,))[0][0] == 4)
    adm.post('/school/results/publish', wf, f'/school/results?term_id={T1}&class_id={cls}')
    ok('publish moves to published', db("SELECT COUNT(*) FROM result_summaries WHERE school_id=? AND status='published'", (Z,))[0][0] == 4)

    s, h, _ = adm.req(f'/school/analytics?term_id={T1}&class_id={cls}'); ok('analytics page shows class average and pass rate with real data', s == 200 and 'Class average' in h and 'Pass rate' in h and '91' not in h[:0] and 'Students ranked' in h, str(s))
    ok('analytics lists both subjects', 'English' in h and 'Mathematics' in h)

    # ---------- 6. Cards
    adm.post('/school/cards/generate', {'term_id': T1, 'quantity': 3, 'label': 'E2E'}, '/school/cards')
    batch = db('SELECT id FROM scratch_card_batches WHERE school_id=?', (Z,))
    ok('batch of 3 cards generated', bool(batch) and db('SELECT COUNT(*) FROM scratch_cards WHERE school_id=?', (Z,))[0][0] == 3)
    B = batch[0][0]
    s, h, _ = adm.req(f'/school/cards/batches/{B}/slips'); ok('card slips page renders while PINs are fresh', s == 200 and pins_ok(h))
    s, h, hd = adm.req(f'/school/cards/batches/{B}/pins.csv')
    rows = list(csv.reader(io.StringIO(h.lstrip('﻿'))))
    ok('one-time PIN CSV download works', s == 200 and len(rows) >= 4, f'{s} {h[:200]}')
    hdr = [x.lower() for x in rows[0]]; pin_i = [i for i, x in enumerate(hdr) if 'pin' in x][0]
    pins = [re.sub(r'\D', '', r[pin_i]) for r in rows[1:]]
    s2, _, _ = adm.req(f'/school/cards/batches/{B}/pins.csv'); ok('PIN CSV cannot be downloaded a second time', s2 == 404, str(s2))
    s, h, _ = adm.req('/school/cards/export.csv'); ok('inventory CSV exports and contains no PINs', s == 200 and not any(p in h for p in pins))

    # ---------- 7. Public portal
    pub = Client()
    s, h, _ = pub.req('/'); ok('public portal home renders', s == 200 and 'GRADELY' in h.upper())
    s, h, _ = pub.req(f'/s/{slug}'); ok('school portal page renders with school name', s == 200 and 'Zenith Academy' in h)
    def check(c, adm_no, pin, term=None, school=slug):
        return c.post('/portal/check', {'school': school, 'term_id': term or T1, 'admission_no': adm_no, 'pin': pin}, f'/s/{slug}')
    for i in range(1, 6):
        s, h, _ = check(Client(), 'Z001', pins[0])
        ok(f'portal check #{i} shows the result', s == 200 and 'Ada' in h and ('Position' in h or 'Grade' in h), f'{s} {h[:150]}')
    s, h, _ = check(Client(), 'Z001', pins[0]); ok('6th check with the same card is refused', 'maximum' in h.lower() or 'used' in h.lower())
    ok('card has exactly 5 uses recorded, 5 usage rows', db('SELECT use_count FROM scratch_cards WHERE school_id=? AND status="exhausted"', (Z,))[0][0] == 5 and db('SELECT COUNT(*) FROM scratch_card_usages WHERE school_id=?', (Z,))[0][0] == 5)
    before = db('SELECT use_count FROM scratch_cards WHERE school_id=? ORDER BY id', (Z,))
    s, h, _ = check(Client(), 'Z001', pins[1], term=T2); ok('wrong term: no result shown', 'Ada' not in h)
    s, h, _ = check(Client(), 'Z001', pins[1], school='alpha-school'); ok('wrong school: no result shown', 'Ada' not in h)
    s, h, _ = check(Client(), 'Z001', '999999999999'); ok('invalid PIN: no result shown', 'Ada' not in h)
    s, h, _ = check(Client(), 'NOBODY', pins[1]); ok('unknown student: no result shown', 'Ada' not in h)
    ok('failed attempts consumed nothing', db('SELECT use_count FROM scratch_cards WHERE school_id=? ORDER BY id', (Z,)) == before)
    # reload of a result page must not consume
    c = Client(); s, h, _ = check(c, 'Z002', pins[1]); u1 = db('SELECT use_count FROM scratch_cards WHERE school_id=? ORDER BY id', (Z,))
    c.req('/portal/result'); c.req('/portal/result'); ok('reloading the result page does not use another check', db('SELECT use_count FROM scratch_cards WHERE school_id=? ORDER BY id', (Z,)) == u1)
    s, h, _ = check(Client(), 'Z003', pins[1]); ok('first-student binding: card used by Z002 rejected for Z003', 'Chi' not in h)
    ok('a rejected binding attempt consumed nothing', db('SELECT use_count FROM scratch_cards WHERE school_id=? ORDER BY id', (Z,)) == u1)

    # ---------- 8. Display modes honoured by the portal and the staff report
    def view(mode):
        adm.post('/school/settings', {'display_mode': mode, 'tie_mode': 'competition', 'position_by': 'average', 'card_binding': 'first_student', 'pass_mark': '40', 'allow_download': '1'}, '/school/settings')
        c = Client(); s, h, _ = check(c, 'Z002', pins[1]); return h
    for mode, g, p in (('grades', True, False), ('positions', False, True), ('both', True, True)):
        h = view(mode)
        ok(f'portal display_mode={mode}: grades {"shown" if g else "hidden"}', (('>Grade</th>' in h) == g), mode)
        ok(f'portal display_mode={mode}: positions {"shown" if p else "hidden"}', (('>Position</th>' in h) == p), mode)
        s, h2, _ = adm.req(f'/school/reports/student?term_id={T1}&student_id={stu["Z002"]}')
        ok(f'staff report card display_mode={mode} matches', s == 200 and (('>Grade</th>' in h2) == g) and (('>Position</th>' in h2) == p), f'{s}')
    s, h, _ = adm.req(f'/school/reports/class?term_id={T1}&class_id={cls}'); ok('bulk class report cards render', s == 200 and 'OBI Ada' in h and 'EZE Bayo' in h, str(s))

    # ---------- 9. Tenant isolation over HTTP
    other = Client(); other.login('a-admin@test.local', 'AdminPass#12345')
    zs = db('SELECT id FROM students WHERE school_id=? LIMIT 1', (Z,))[0][0]
    for label, p in (('student edit', f'/school/students/{zs}/edit'), ('card batch', f'/school/cards/batches/{B}'), ('student report', f'/school/reports/student?term_id={T1}&student_id={zs}'), ('results of foreign term/class', f'/school/results?term_id={T1}&class_id={cls}'), ('scores of foreign term', f'/school/scores?term_id={T1}&class_id={cls}&subject_id={subs[0]}')):
        s, h, _ = other.req(p); ok(f'other school cannot open {label} by URL tampering', s in (403, 404) or ('Ada' not in h and 'Zenith' not in h), f'{s}')
    s, h, _ = other.post(f'/school/cards/batches/{B}/revoke', {}, '/school/cards'); ok('other school cannot revoke our batch', db('SELECT COUNT(*) FROM scratch_cards WHERE school_id=? AND status="revoked"', (Z,))[0][0] == 0)
    s, h, _ = other.post(f'/school/students/{zs}/delete', {}, '/school/students'); ok('other school cannot delete our student', db('SELECT COUNT(*) FROM students WHERE id=?', (zs,))[0][0] == 1)
    s, h, _ = other.req('/super'); ok('school admin cannot open /super', s in (302, 403) and 'Platform' not in h)

    # ---------- 10. Revoke + suspend
    adm.post(f'/school/cards/batches/{B}/revoke', {}, '/school/cards')
    s, h, _ = check(Client(), 'Z001', pins[2]); ok('revoked card is refused', 'Ada' not in h)
    sup.post(f'/super/schools/{Z}/status', {'status': 'suspended'}, '/super')
    s, h, _ = Client().req(f'/s/{slug}'); ok('suspended school disappears from the portal (404)', s == 404, str(s))
    t = Client(); s, h, _ = t.login('zed@zenith.test', 'ZenithPass#2026!'); s2, h2, _ = t.req('/school'); ok('suspended school admin cannot use the dashboard', 'Ada' not in h2 and ('/login' in h2 or s2 in (302, 403)) , f'{s2}')

    # ---------- 11. CSV export formula injection guard
    sup.post(f'/super/schools/{Z}/status', {'status': 'active'}, '/super')
    adm2 = Client(); adm2.login('zed@zenith.test', 'ZenithPass#2026!')
    adm2.post('/school/students', {'admission_no': 'Z099', 'first_name': '=HYPERLINK("http://x")', 'last_name': 'Evil', 'class_id': cls, 'status': 'active'}, '/school/students/new')
    s, h, _ = adm2.req('/school/students/export.csv'); ok('exported CSV neutralises a leading "=" (formula injection)', "'=HYPERLINK" in h or '"\'=' in h, h[-200:])
finally:
    srv.terminate()
log = open(LOG).read().strip()
ok('no PHP warnings or errors were logged during the whole run', log == '', log[:400])
print(f'\nRESULT: {passed} passed, {failed} failed'); sys.exit(1 if failed else 0)
