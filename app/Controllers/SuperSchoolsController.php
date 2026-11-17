<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{AuditLog, DB, Http, Session, View};
use App\Services\{Notify, Passwords, SchoolProvisioner};

/** Super Administrator: register and manage schools. */
final class SuperSchoolsController extends BaseController
{
    private function school(int $id): array
    {
        $s = DB::one('SELECT * FROM schools WHERE id = ?', [$id]);
        if ($s === null) {
            Http::abort(404);
        }
        return $s;
    }

    public function index(): void
    {
        $q = Http::input('q');
        $status = Http::input('status');
        $sql = 'SELECT s.*,
                  (SELECT COUNT(*) FROM students st WHERE st.school_id = s.id) AS students,
                  (SELECT COUNT(*) FROM users u WHERE u.school_id = s.id) AS staff,
                  (SELECT COUNT(*) FROM scratch_cards c WHERE c.school_id = s.id) AS cards
                FROM schools s WHERE 1 = 1';
        $p = [];
        if ($q !== '') {
            $sql .= ' AND (s.name LIKE ? OR s.email LIKE ?)';
            $p[] = $p[] = '%' . $q . '%';
        }
        if (in_array($status, ['active', 'suspended', 'inactive'], true)) {
            $sql .= ' AND s.status = ?';
            $p[] = $status;
        }
        View::render('super/schools', ['title' => 'Schools', 'schools' => DB::all($sql . ' ORDER BY s.id DESC', $p), 'q' => $q, 'status' => $status]);
    }

    public function create(): void
    {
        View::render('super/school_new', ['title' => 'Register a school']);
    }

    public function store(): void
    {
        $name = $this->text('name');
        $email = strtolower((string) $this->text('email'));
        $phone = $this->text('phone', 40);
        $address = $this->text('address', 255);
        $adminName = $this->text('admin_name', 150);
        $adminEmail = strtolower((string) $this->text('admin_email'));
        $password = (string) ($_POST['admin_password'] ?? '');
        $old = ['name' => $name, 'email' => $email, 'phone' => $phone, 'address' => $address, 'admin_name' => $adminName, 'admin_email' => $adminEmail];
        $back = '/super/schools/new';

        if ($name === null || $name === '') {
            $this->fail('Enter the school name (up to 190 characters).', $back, $old);
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->fail('The school email address is not valid.', $back, $old);
        }
        if ($phone === null || $address === null) {
            $this->fail('The phone number or address is too long.', $back, $old);
        }
        if ($adminName === null || $adminName === '') {
            $this->fail('Enter the administrator\'s name.', $back, $old);
        }
        if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL) || strlen($adminEmail) > 190) {
            $this->fail('Enter a valid administrator email address.', $back, $old);
        }
        if ((int) DB::val('SELECT COUNT(*) FROM users WHERE email = ?', [$adminEmail]) > 0) {
            $this->fail('That administrator email is already used by another account.', $back, $old);
        }
        $generated = false;
        if ($password === '') {
            $password = Passwords::generate();
            $generated = true;
        } elseif (strlen($password) < 10) {
            $this->fail('The password must be at least 10 characters (or leave it blank to generate one).', $back, $old);
        }

        try {
            $sid = SchoolProvisioner::create(
                ['name' => $name, 'email' => $email ?: null, 'phone' => $phone ?: null, 'address' => $address ?: null],
                ['name' => $adminName, 'email' => $adminEmail, 'password' => $password]
            );
        } catch (\PDOException $e) {
            if (self::isDuplicate($e)) {
                $this->fail('A school or account with those details already exists.', $back, $old);
            }
            throw $e;
        }
        AuditLog::record('school.created', 'school', $sid, ['name' => $name], $sid);
        $msg = 'School registered. Administrator login: ' . $adminEmail;
        $msg .= $generated ? ' · temporary password: ' . $password . ' (shown once, copy it now).' : '.';
        $this->ok($msg, '/super/schools/' . $sid);
    }

    public function show(int $id): void
    {
        $s = $this->school($id);
        $count = static fn(string $sql): int => (int) DB::val($sql, [$id]);
        View::render('super/school_show', [
            'title' => $s['name'],
            's' => $s,
            'admins' => DB::all('SELECT id, name, email, role, status, last_login_at FROM users WHERE school_id = ? ORDER BY role, name', [$id]),
            'counts' => [
                'students' => $count('SELECT COUNT(*) FROM students WHERE school_id = ?'),
                'classes' => $count('SELECT COUNT(*) FROM classes WHERE school_id = ?'),
                'subjects' => $count('SELECT COUNT(*) FROM subjects WHERE school_id = ?'),
                'published' => $count("SELECT COUNT(*) FROM result_summaries WHERE school_id = ? AND status = 'published'"),
                'cards' => $count('SELECT COUNT(*) FROM scratch_cards WHERE school_id = ?'),
                'cards_used' => $count('SELECT COUNT(*) FROM scratch_cards WHERE school_id = ? AND use_count > 0'),
            ],
            'activity' => DB::all('SELECT a.action, a.created_at, u.name AS user_name FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id WHERE a.school_id = ? ORDER BY a.id DESC LIMIT 8', [$id]),
        ]);
    }

    public function update(int $id): void
    {
        $this->school($id);
        $name = $this->text('name');
        $email = strtolower((string) $this->text('email'));
        $phone = $this->text('phone', 40);
        $address = $this->text('address', 255);
        $plan = $this->text('plan', 40);
        $lic = Http::input('license_expires_on');
        $teacherLimitRaw = trim(Http::input('teacher_limit'));
        $teacherLimit = $teacherLimitRaw === '' ? null : (int) $teacherLimitRaw;
        $back = '/super/schools/' . $id;
        if ($name === null || $name === '' || $phone === null || $address === null || $plan === null) {
            $this->fail('Check the school details: the name is required and some values are too long.', $back);
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->fail('The school email address is not valid.', $back);
        }
        if ($lic !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $lic)) {
            $this->fail('The licence expiry must be a date.', $back);
        }
        if ($teacherLimitRaw !== '' && !in_array($teacherLimit, [5, 10, 20], true)) {
            $this->fail('Choose unlimited, 5, 10 or 20 active teachers.', $back);
        }
        DB::run(
            'UPDATE schools SET name = ?, email = ?, phone = ?, address = ?, plan = ?, license_expires_on = ?, teacher_limit = ? WHERE id = ?',
            [$name, $email ?: null, $phone ?: null, $address ?: null, $plan ?: 'standard', $lic ?: null, $teacherLimit, $id]
        );
        AuditLog::record('school.updated', 'school', $id, ['teacher_limit' => $teacherLimit], $id);
        $this->ok('School details saved.', $back);
    }

    public function status(int $id): void
    {
        $s = $this->school($id);
        $new = Http::input('status');
        if (!in_array($new, ['active', 'suspended', 'inactive'], true)) {
            $this->fail('Unknown status.', '/super/schools/' . $id);
        }
        DB::run('UPDATE schools SET status = ? WHERE id = ?', [$new, $id]);
        AuditLog::record('school.status.' . $new, 'school', $id, ['from' => $s['status']], $id);
        $this->ok($s['name'] . ' is now ' . $new . '.', '/super/schools/' . $id);
    }

    public function resetPassword(int $id, int $user_id): void
    {
        $this->school($id);
        $u = DB::one("SELECT id, name, email FROM users WHERE id = ? AND school_id = ? AND role IN ('school_admin','teacher')", [$user_id, $id]);
        if ($u === null) {
            Http::abort(404);
        }
        $pw = Passwords::generate();
        DB::run('UPDATE users SET password_hash = ? WHERE id = ?', [\App\Core\Auth::hash($pw), $u['id']]);
        AuditLog::record('user.password_reset_by_platform', 'user', $u['id'], [], $id);
        Notify::user($id, (int) $u['id'], 'Your password was reset', 'The platform administrator reset your password. Please change it after signing in.');
        $this->ok('New password for ' . $u['email'] . ': ' . $pw . ' (shown once, copy it now).', '/super/schools/' . $id);
    }
}
