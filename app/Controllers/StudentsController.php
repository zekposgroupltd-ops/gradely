<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{AuditLog, DB, Http, View};
use App\Services\Importer;

final class StudentsController extends BaseController
{
    private const BACK = '/school/students';
    private const PER_PAGE = 25;

    /** Spreadsheet header aliases → canonical field. */
    private const ALIASES = [
        'admission_no' => 'admission_no', 'admission_number' => 'admission_no', 'admission' => 'admission_no', 'student_id' => 'admission_no', 'reg_no' => 'admission_no', 'student_no' => 'admission_no',
        'first_name' => 'first_name', 'firstname' => 'first_name', 'first' => 'first_name',
        'last_name' => 'last_name', 'lastname' => 'last_name', 'surname' => 'last_name',
        'other_name' => 'other_name', 'other_names' => 'other_name', 'middle_name' => 'other_name',
        'gender' => 'gender', 'sex' => 'gender',
        'date_of_birth' => 'date_of_birth', 'dob' => 'date_of_birth', 'birth_date' => 'date_of_birth',
        'class' => 'class', 'class_name' => 'class',
        'arm' => 'arm', 'class_arm' => 'arm', 'section' => 'arm',
    ];

    public function index(): void
    {
        $sid = $this->sid();
        $q = Http::input('q');
        $classId = (int) Http::input('class_id');
        $page = max(1, (int) Http::input('page', '1'));

        $where = 'st.school_id = ?';
        $p = [$sid];
        if ($q !== '') {
            $where .= ' AND (st.first_name LIKE ? OR st.last_name LIKE ? OR st.other_name LIKE ? OR st.admission_no LIKE ?)';
            array_push($p, "%$q%", "%$q%", "%$q%", "%$q%");
        }
        if ($classId > 0) {
            $where .= ' AND st.class_id = ?';
            $p[] = $classId;
        }
        $total = (int) DB::val("SELECT COUNT(*) FROM students st WHERE $where", $p);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($page, $pages);
        $offset = ($page - 1) * self::PER_PAGE; // ints only: safe to inline
        $rows = DB::all(
            "SELECT st.*, c.name AS class_name, a.name AS arm_name
             FROM students st
             JOIN classes c ON c.id = st.class_id AND c.school_id = st.school_id
             LEFT JOIN class_arms a ON a.id = st.arm_id AND a.school_id = st.school_id
             WHERE $where ORDER BY c.level_order, c.name, st.last_name, st.first_name LIMIT " . self::PER_PAGE . " OFFSET $offset",
            $p
        );
        View::render('school/students', [
            'title' => 'Students', 'rows' => $rows, 'q' => $q, 'classId' => $classId,
            'classes' => $this->classOptions(), 'page' => $page, 'pages' => $pages, 'total' => $total,
        ]);
    }

    private function classOptions(): array
    {
        $o = [];
        foreach (DB::all('SELECT id, name FROM classes WHERE school_id = ? ORDER BY level_order, name', [$this->sid()]) as $c) {
            $o[$c['id']] = $c['name'];
        }
        return $o;
    }

    private function armsByClass(): array
    {
        $o = [];
        foreach (DB::all('SELECT id, class_id, name FROM class_arms WHERE school_id = ? ORDER BY name', [$this->sid()]) as $a) {
            $o[$a['class_id']][] = $a;
        }
        return $o;
    }

    public function create(): void
    {
        View::render('school/student_form', ['title' => 'Add student', 'student' => null, 'classes' => $this->classOptions(), 'arms' => $this->armsByClass()]);
    }

    public function edit(int $id): void
    {
        View::render('school/student_form', ['title' => 'Edit student', 'student' => $this->owned('students', $id), 'classes' => $this->classOptions(), 'arms' => $this->armsByClass()]);
    }

    /** @return array{0:?string,1:array} error message or null, plus cleaned data */
    private function clean(array $in, ?int $ignoreId): array
    {
        $d = [
            'admission_no' => trim((string) ($in['admission_no'] ?? '')),
            'first_name' => trim((string) ($in['first_name'] ?? '')),
            'last_name' => trim((string) ($in['last_name'] ?? '')),
            'other_name' => trim((string) ($in['other_name'] ?? '')),
            'gender' => null, 'date_of_birth' => null,
            'class_id' => (int) ($in['class_id'] ?? 0),
            'arm_id' => (int) ($in['arm_id'] ?? 0) ?: null,
            'status' => in_array($in['status'] ?? 'active', ['active', 'graduated', 'withdrawn'], true) ? ($in['status'] ?? 'active') : 'active',
        ];
        $g = strtoupper(substr(trim((string) ($in['gender'] ?? '')), 0, 1));
        if ($g !== '' && !in_array($g, ['M', 'F'], true)) {
            return ['Gender must be M/F (Male/Female).', $d];
        }
        $d['gender'] = $g ?: null;

        if (($d['admission_no'] === '' && $ignoreId !== null) || strlen($d['admission_no']) > 40 || ($d['admission_no'] !== '' && !preg_match('/^[A-Za-z0-9\/._-]+$/', $d['admission_no']))) {
            return ['Enter a valid student ID, or leave it blank when adding a new student to generate one automatically.', $d];
        }
        foreach (['first_name' => 'First name', 'last_name' => 'Last name'] as $k => $label) {
            if ($d[$k] === '' || mb_strlen($d[$k]) > 80) {
                return ["$label is required (up to 80 characters).", $d];
            }
        }
        if (mb_strlen($d['other_name']) > 80) {
            return ['Other name is too long.', $d];
        }
        $dob = trim((string) ($in['date_of_birth'] ?? ''));
        if ($dob !== '') {
            $iso = $this->date($dob);
            if ($iso === null) {
                return ['Date of birth must be a valid date (YYYY-MM-DD).', $d];
            }
            $d['date_of_birth'] = $iso;
        }
        if ($d['class_id'] <= 0 || DB::val('SELECT COUNT(*) FROM classes WHERE id = ? AND school_id = ?', [$d['class_id'], $this->sid()]) == 0) {
            return ['Choose a valid class.', $d];
        }
        if ($d['arm_id'] !== null && DB::val('SELECT COUNT(*) FROM class_arms WHERE id = ? AND class_id = ? AND school_id = ?', [$d['arm_id'], $d['class_id'], $this->sid()]) == 0) {
            return ['That arm does not belong to the chosen class.', $d];
        }
        $dup = $d['admission_no'] !== '' ? DB::val('SELECT id FROM students WHERE school_id = ? AND admission_no = ?', [$this->sid(), $d['admission_no']]) : null;
        if ($dup !== null && (int) $dup !== $ignoreId) {
            return ['Another student already uses that student ID.', $d];
        }
        return [null, $d];
    }

    /** Accepts YYYY-MM-DD, DD/MM/YYYY, DD-MM-YYYY and Excel serial numbers. */
    private function date(string $v): ?string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
            $y = (int) substr($v, 0, 4); $m = (int) substr($v, 5, 2); $dd = (int) substr($v, 8, 2);
        } elseif (preg_match('#^(\d{1,2})[/-](\d{1,2})[/-](\d{4})$#', $v, $x)) {
            [$dd, $m, $y] = [(int) $x[1], (int) $x[2], (int) $x[3]];
        } elseif (preg_match('/^\d{4,6}(\.\d+)?$/', $v) && (float) $v > 1000 && (float) $v < 80000) {
            return gmdate('Y-m-d', (int) round(((float) $v - 25569) * 86400));
        } else {
            return null;
        }
        return checkdate($m, $dd, $y) && $y > 1900 ? sprintf('%04d-%02d-%02d', $y, $m, $dd) : null;
    }

    /** Generate the next school-scoped ID from the configured prefix (e.g. SGW0001). */
    private function nextAdmissionNo(int $sid, array $school, array $reserved = []): string
    {
        $prefix = strtoupper((string) ($school['admission_prefix'] ?? ''));
        if ($prefix === '') {
            preg_match_all('/[A-Za-z0-9]+/u', (string) $school['name'], $words);
            $prefix = implode('', array_map(static fn($w) => strtoupper(substr($w, 0, 1)), $words[0] ?? []));
            $prefix = substr($prefix ?: 'STD', 0, 8);
        }
        $used = DB::all('SELECT admission_no FROM students WHERE school_id = ? AND admission_no LIKE ?', [$sid, $prefix . '%']);
        $max = 0;
        foreach ($used as $row) {
            if (preg_match('/^' . preg_quote($prefix, '/') . '(\d{4,})$/i', (string) $row['admission_no'], $m)) { $max = max($max, (int) $m[1]); }
        }
        do { $candidate = $prefix . str_pad((string) (++$max), 4, '0', STR_PAD_LEFT); }
        while (isset($reserved[strtolower($candidate)]) || DB::val('SELECT COUNT(*) FROM students WHERE school_id = ? AND admission_no = ?', [$sid, $candidate]) > 0);
        return $candidate;
    }

    public function store(): void
    {
        [$err, $d] = $this->clean($_POST, null);
        if ($err !== null) {
            $this->fail($err, '/school/students/new', $_POST);
        }
        $sid = $this->sid();
        $id = DB::transaction(function () use ($sid, &$d): int {
            $schoolSql = 'SELECT name, admission_prefix FROM schools WHERE id = ?';
            if (DB::isMysql()) { $schoolSql .= ' FOR UPDATE'; }
            $school = DB::one($schoolSql, [$sid]);
            if ($school === null) { throw new \RuntimeException('School account not found.'); }
            if ($d['admission_no'] === '') { $d['admission_no'] = $this->nextAdmissionNo($sid, $school); }
            return DB::insert(
                'INSERT INTO students (school_id, admission_no, first_name, last_name, other_name, gender, date_of_birth, class_id, arm_id, status) VALUES (?,?,?,?,?,?,?,?,?,?)',
                [$sid, $d['admission_no'], $d['first_name'], $d['last_name'], $d['other_name'] ?: null, $d['gender'], $d['date_of_birth'], $d['class_id'], $d['arm_id'], 'active']
            );
        });
        AuditLog::record('student.created', 'student', $id, ['admission_no' => $d['admission_no']]);
        $this->ok($d['first_name'] . ' ' . $d['last_name'] . ' added.', self::BACK);
    }

    public function update(int $id): void
    {
        $this->owned('students', $id);
        [$err, $d] = $this->clean($_POST, $id);
        if ($err !== null) {
            $this->fail($err, "/school/students/$id/edit", $_POST);
        }
        DB::run(
            'UPDATE students SET admission_no=?, first_name=?, last_name=?, other_name=?, gender=?, date_of_birth=?, class_id=?, arm_id=?, status=? WHERE id=? AND school_id=?',
            [$d['admission_no'], $d['first_name'], $d['last_name'], $d['other_name'] ?: null, $d['gender'], $d['date_of_birth'], $d['class_id'], $d['arm_id'], $d['status'], $id, $this->sid()]
        );
        AuditLog::record('student.updated', 'student', $id);
        $this->ok('Student details saved.', self::BACK);
    }

    public function delete(int $id): void
    {
        $s = $this->owned('students', $id);
        $this->tryDelete(
            fn() => DB::run('DELETE FROM students WHERE id = ? AND school_id = ?', [$id, $this->sid()]),
            $s['first_name'] . ' ' . $s['last_name'] . ' deleted.',
            'This student already has scores or results. Set their status to "withdrawn" instead of deleting.',
            self::BACK
        );
    }

    public function importForm(): void
    {
        View::render('school/student_import', ['title' => 'Import students', 'report' => null]);
    }

    public function template(): void
    {
        $this->csv('students-template.csv', [['admission_no', 'first_name', 'last_name', 'other_name', 'gender', 'date_of_birth', 'class', 'arm'], ['', 'Ada', 'Obi', '', 'F', '2014-05-21', 'JSS 1', 'A']]);
    }

    public function export(): void
    {
        $rows = [['admission_no', 'first_name', 'last_name', 'other_name', 'gender', 'date_of_birth', 'class', 'arm', 'status']];
        foreach (DB::all(
            'SELECT st.*, c.name AS class_name, a.name AS arm_name FROM students st JOIN classes c ON c.id = st.class_id AND c.school_id = st.school_id
             LEFT JOIN class_arms a ON a.id = st.arm_id AND a.school_id = st.school_id WHERE st.school_id = ? ORDER BY c.level_order, st.last_name, st.first_name',
            [$this->sid()]
        ) as $s) {
            $rows[] = [$s['admission_no'], $s['first_name'], $s['last_name'], $s['other_name'], $s['gender'], $s['date_of_birth'], $s['class_name'], $s['arm_name'], $s['status']];
        }
        AuditLog::record('students.exported', 'student', null, ['rows' => count($rows) - 1]);
        $this->csv('students.csv', $rows);
    }

    private function csv(string $name, array $rows): never
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        $h = fopen('php://output', 'w');
        fwrite($h, "\xEF\xBB\xBF");
        foreach ($rows as $r) {
            fputcsv($h, array_map('csv_safe', $r), ',', '"', '');
        }
        fclose($h);
        exit;
    }

    public function import(): void
    {
        try {
            $data = Importer::read($_FILES['file'] ?? []);
        } catch (\RuntimeException $e) {
            $this->fail($e->getMessage(), '/school/students/import');
        }

        // Map the file's headers onto our fields.
        $map = [];
        foreach ($data['headers'] as $h) {
            if (isset(self::ALIASES[$h])) {
                $map[$h] = self::ALIASES[$h];
            }
        }
        $missing = array_diff(['first_name', 'last_name', 'class'], array_values($map));
        if ($missing) {
            $this->fail('The file is missing required column(s): ' . implode(', ', $missing) . '. Download the template to see the expected headers.', '/school/students/import');
        }

        $sid = $this->sid();
        $classes = [];
        foreach (DB::all('SELECT id, name FROM classes WHERE school_id = ?', [$sid]) as $c) {
            $classes[mb_strtolower($c['name'])] = (int) $c['id'];
        }
        $arms = [];
        foreach (DB::all('SELECT id, class_id, name FROM class_arms WHERE school_id = ?', [$sid]) as $a) {
            $arms[$a['class_id'] . '|' . mb_strtolower($a['name'])] = (int) $a['id'];
        }

        $errors = [];
        $good = [];
        $seen = [];
        foreach ($data['rows'] as $line => $raw) {
            $r = [];
            foreach ($raw as $h => $v) {
                if (isset($map[$h])) {
                    $r[$map[$h]] = $v;
                }
            }
            $classId = $classes[mb_strtolower($r['class'] ?? '')] ?? 0;
            if ($classId === 0) {
                $errors[] = [$line, $r['admission_no'] ?? '', 'Unknown class "' . ($r['class'] ?? '') . '". Create it first under Classes.'];
                continue;
            }
            $armId = 0;
            if (($r['arm'] ?? '') !== '') {
                $armId = $arms[$classId . '|' . mb_strtolower($r['arm'])] ?? 0;
                if ($armId === 0) {
                    $errors[] = [$line, $r['admission_no'] ?? '', 'Unknown arm "' . $r['arm'] . '" for ' . $r['class'] . '.'];
                    continue;
                }
            }
            [$err, $d] = $this->clean($r + ['class_id' => $classId, 'arm_id' => $armId], null);
            $idKey = strtolower($d['admission_no']);
            if ($err === null && $d['admission_no'] !== '' && isset($seen[$idKey])) {
                $err = 'Duplicate student ID inside the file (also on row ' . $seen[$idKey] . ').';
            }
            if ($err !== null) {
                $errors[] = [$line, $r['admission_no'] ?? '', $err];
                continue;
            }
            if ($d['admission_no'] !== '') { $seen[$idKey] = $line; }
            $good[] = $d;
        }

        DB::transaction(function () use ($good, $sid): void {
            $lock = 'SELECT name, admission_prefix FROM schools WHERE id = ?'; if (DB::isMysql()) { $lock .= ' FOR UPDATE'; }
            $school = DB::one($lock, [$sid]);
            if ($school === null) { throw new \RuntimeException('School account not found.'); }
            $reserved = [];
            foreach ($good as $row) { if ($row['admission_no'] !== '') { $reserved[strtolower($row['admission_no'])] = true; } }
            foreach ($good as $d) {
                $admissionNo = $d['admission_no'] !== '' ? $d['admission_no'] : $this->nextAdmissionNo($sid, $school, $reserved);
                $reserved[strtolower($admissionNo)] = true;
                DB::insert(
                    'INSERT INTO students (school_id, admission_no, first_name, last_name, other_name, gender, date_of_birth, class_id, arm_id, status) VALUES (?,?,?,?,?,?,?,?,?,?)',
                    [$sid, $admissionNo, $d['first_name'], $d['last_name'], $d['other_name'] ?: null, $d['gender'], $d['date_of_birth'], $d['class_id'], $d['arm_id'], 'active']
                );
            }
        });
        AuditLog::record('students.imported', 'student', null, ['imported' => count($good), 'skipped' => count($errors)]);
        View::render('school/student_import', ['title' => 'Import students', 'report' => ['imported' => count($good), 'errors' => $errors, 'total' => count($data['rows'])]]);
    }
}
