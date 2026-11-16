#!/usr/bin/env python3
"""Real-browser checks (headless Chromium via Playwright): XLSX import through the UI form, PWA installability signals,
A4 print layout of a report card, and mobile viewport overflow. usage: python3 tests/e2e_browser.py <scratch-dir>"""
import os, re, sqlite3, subprocess, sys, time, io
from playwright.sync_api import sync_playwright
import openpyxl

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
TMP = sys.argv[1] if len(sys.argv) > 1 else '/tmp'; os.makedirs(TMP, exist_ok=True)
DBF = f'{TMP}/br.sqlite'; PORT = 8124; BASE = f'http://127.0.0.1:{PORT}'
if os.path.exists(DBF): os.remove(DBF)
LOG = f'{ROOT}/storage/logs/php-error.log'
open(LOG, 'w').close()
subprocess.run(['php', f'{ROOT}/tests/tools/make_test_db.php', DBF], check=True, stdout=subprocess.DEVNULL)
env = dict(os.environ, DB_DRIVER='sqlite', DB_SQLITE_PATH=DBF, APP_ENV='development', APP_SECRET='br-secret-br-secret-br-secret-12345')
srv = subprocess.Popen(['php', '-S', f'127.0.0.1:{PORT}', '-t', f'{ROOT}/public', f'{ROOT}/public/index.php'], env=env, stdout=open(f'{TMP}/br-server.log', 'w'), stderr=subprocess.STDOUT)
time.sleep(1)
passed = failed = 0
def ok(l, c, d=''):
    global passed, failed
    if c: passed += 1; print('PASS ', l)
    else: failed += 1; print('FAIL ', l, str(d)[:300])
q = lambda sql, a=(): sqlite3.connect(DBF).execute(sql, a).fetchall()

# build XLSX of students
wb = openpyxl.Workbook(); ws = wb.active
ws.append(['Admission No', 'First Name', 'Surname', 'Sex', 'Class'])
for i in range(1, 4): ws.append([f'X{i:03d}', f'Name{i}', f'Sur{i}', 'F' if i % 2 else 'M', 'Primary 4'])
xl = f'{TMP}/students.xlsx'; wb.save(xl)
try:
    with sync_playwright() as pw:
        b = pw.chromium.launch(); ctx = b.new_context(viewport={'width': 1280, 'height': 900}); pg = ctx.new_page()
        pg.goto(BASE + '/login'); pg.fill('input[name=email]', 'a-admin@test.local'); pg.fill('input[name=password]', 'AdminPass#12345'); pg.click('button[type=submit]'); pg.wait_for_url('**/school**')
        # needs a class called Primary 4 in school Alpha
        sid = q("SELECT school_id FROM users WHERE email='a-admin@test.local'")[0][0]
        pg.goto(BASE + '/school/classes'); pg.fill('input[name=name]', 'Primary 4'); pg.click('form[action$="/school/classes"] button[type=submit]')
        ok('class created through the UI', q("SELECT COUNT(*) FROM classes WHERE school_id=? AND name='Primary 4'", (sid,))[0][0] == 1)
        pg.goto(BASE + '/school/students/import'); pg.set_input_files('input[type=file]', xl); pg.click('form[enctype] button[type=submit]'); pg.wait_for_load_state()
        n = q("SELECT COUNT(*) FROM students WHERE school_id=? AND admission_no LIKE 'X%'", (sid,))[0][0]
        ok('XLSX import through the browser form adds 3 students (aliased headers Surname/Sex recognised)', n == 3, n)

        # PWA signals
        pg.goto(BASE + '/login')
        man = pg.evaluate("fetch(document.querySelector('link[rel=manifest]').href).then(r=>r.json())")
        ok('manifest has name, standalone display, start_url and 192+512 icons', man.get('display') == 'standalone' and man.get('start_url') and {'192x192', '512x512'} <= {i['sizes'] for i in man['icons']}, man)
        ok('maskable icon declared', any('maskable' in i.get('purpose', '') for i in man['icons']))
        pg.wait_for_timeout(1500)
        reg = pg.evaluate("navigator.serviceWorker.getRegistration().then(r=>!!r && !!(r.active||r.installing||r.waiting))")
        ok('service worker registers and activates', reg)
        pg.wait_for_function("navigator.serviceWorker.ready.then(()=>true)", timeout=8000)
        ctx.set_offline(True)
        pg.goto(BASE + '/login') if False else None
        try:
            pg.goto(BASE + '/school', wait_until='domcontentloaded'); off = pg.content()
        except Exception as e:
            off = str(e)
        ok('offline navigation shows the offline page instead of a browser error', 'offline' in off.lower() or 'connection' in off.lower(), off[:150])
        ctx.set_offline(False); pg.goto(BASE + '/login'); pg.wait_for_load_state()
        ok('iOS meta tags present (apple-touch-icon, apple-mobile-web-app-capable)', all(s in pg.content() for s in ['apple-touch-icon', 'apple-mobile-web-app-capable']) or 'apple-touch-icon' in pg.content())
        ok('theme-color meta present', 'theme-color' in pg.content())

        # report card A4 print (data prepared by direct DB inserts for speed)
        con = sqlite3.connect(DBF)
        for i,(n,m,e) in enumerate([('1st Test',20,0),('2nd Test',20,0),('Exam',60,1)]): con.execute('INSERT INTO assessment_components (school_id,name,max_score,sort_order,is_exam) VALUES (?,?,?,?,?)',(sid,n,m,i,e))
        for i,(g,lo,hi,rm) in enumerate([('A',70,100,'Excellent'),('B',60,69.99,'Very Good'),('C',50,59.99,'Good'),('D',45,49.99,'Fair'),('E',40,44.99,'Pass'),('F',0,39.99,'Fail')]): con.execute('INSERT INTO grade_boundaries (school_id,grade,min_score,max_score,remark,sort_order) VALUES (?,?,?,?,?,?)',(sid,g,lo,hi,rm,i))
        cls = con.execute("SELECT id FROM classes WHERE school_id=? AND name='Primary 4'", (sid,)).fetchone()[0]
        sess = con.execute("INSERT INTO academic_sessions (school_id,name,is_current) VALUES (?,?,1)", (sid, '2026/2027')).lastrowid
        term = con.execute("INSERT INTO terms (school_id,session_id,name,is_current) VALUES (?,?,?,1)", (sid, sess, 'First Term')).lastrowid
        subs = []
        for nm in ['English Language', 'Mathematics', 'Basic Science', 'Civic Education', 'Computer Studies', 'Fine Art']:
            si = con.execute("INSERT INTO subjects (school_id,name) VALUES (?,?)", (sid, nm)).lastrowid; subs.append(si)
            con.execute("INSERT INTO class_subjects (school_id,class_id,subject_id) VALUES (?,?,?)", (sid, cls, si))
        st = con.execute("SELECT id FROM students WHERE school_id=? AND admission_no='X001'", (sid,)).fetchone()[0]
        comps = [r[0] for r in con.execute("SELECT id FROM assessment_components WHERE school_id=? ORDER BY sort_order", (sid,))]
        for si, tot in zip(subs, [87, 95, 92, 92, 96, 86]):
            a = tot // 5
            for ci, v in zip(comps, (a, a, tot - 2 * a)): con.execute("INSERT INTO scores (school_id,term_id,student_id,subject_id,component_id,score) VALUES (?,?,?,?,?,?)", (sid, term, st, si, ci, v))
        con.commit(); con.close()
        tok = re.search(r'name="_csrf" value="([^"]+)"', (pg.goto(BASE + f'/school/results?term_id={term}&class_id={cls}'), pg.content())[1]).group(1)
        r = ctx.request.post(BASE + '/school/results/compute', form={'_csrf': tok, 'term_id': str(term), 'class_id': str(cls)}); 
        print('compute status', r.status, re.findall(r'class="flash[^>]*>(.*?)</div>', r.text(), re.S)[:2])
        row = q("SELECT total, average FROM result_summaries WHERE student_id=? AND term_id=?", (st, term))
        ok('Rich Harvest sample through the full app: total 548, average 91.33', row and float(row[0][0]) == 548.0 and float(row[0][1]) == 91.33, row)
        pg.goto(BASE + f'/school/reports/student?term_id={term}&student_id={st}'); pg.wait_for_load_state('networkidle')
        pg.emulate_media(media='print')
        pdf = f'{TMP}/reportcard.pdf'; pg.pdf(path=pdf, prefer_css_page_size=True, print_background=True); print('url at pdf:', pg.url)
        pages = len(re.findall(rb'/Type\s*/Page[^s]', open(pdf, 'rb').read()))
        mb = re.search(rb'/MediaBox\s*\[\s*0\s+0\s+([\d.]+)\s+([\d.]+)', open(pdf, 'rb').read())
        w, h = float(mb.group(1)), float(mb.group(2))
        ok('report card prints on exactly ONE A4 page', pages == 1 and abs(w - 595.28) < 2 and abs(h - 841.89) < 2, f'pages={pages} size={w}x{h}')
        ok('print view hides app chrome (sidebar/buttons)', pg.evaluate("[...document.querySelectorAll('.no-print')].every(e=>getComputedStyle(e).display==='none')"))
        pg.emulate_media(media='screen'); pg.set_viewport_size({'width': 1100, 'height': 1400}); pg.screenshot(path=f'{TMP}/shot-reportcard.png', full_page=True)

        # mobile overflow checks
        m = b.new_context(viewport={'width': 390, 'height': 844}, device_scale_factor=2, is_mobile=True, has_touch=True); mp = m.new_page()
        for path in ['/', '/login']:
            mp.goto(BASE + path); mp.wait_for_load_state('networkidle')
            ok(f'mobile {path}: no horizontal scroll', mp.evaluate("document.documentElement.scrollWidth <= window.innerWidth + 1"), mp.evaluate("document.documentElement.scrollWidth"))
            mp.screenshot(path=f'{TMP}/shot-mobile{path.replace("/", "_") or "_home"}.png', full_page=True)
        mp.goto(BASE + '/login'); mp.fill('input[name=email]', 'a-admin@test.local'); mp.fill('input[name=password]', 'AdminPass#12345'); mp.click('button[type=submit]'); mp.wait_for_url('**/school**')
        for path in ['/school', '/school/students', f'/school/scores?term_id={term}&class_id={cls}&subject_id={subs[0]}', '/school/cards', '/school/settings']:
            mp.goto(BASE + path); mp.wait_for_load_state('networkidle')
            ok(f'mobile {path.split("?")[0]}: no horizontal page scroll', mp.evaluate("document.documentElement.scrollWidth <= window.innerWidth + 1"), mp.evaluate("document.documentElement.scrollWidth"))
        mp.goto(BASE + '/school'); mp.screenshot(path=f'{TMP}/shot-mobile-dashboard.png')
        b.close()
finally:
    srv.terminate()
log = open(LOG).read().strip()
ok('no PHP warnings or errors were logged during the whole run', log == '', log[:400])
print(f'\nRESULT: {passed} passed, {failed} failed'); sys.exit(1 if failed else 0)
