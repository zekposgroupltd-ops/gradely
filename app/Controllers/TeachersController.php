<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Auth, AuditLog, DB, Http, View};
use App\Services\Passwords;

/** School administrator: teacher accounts, permissions and class/subject assignments. */
final class TeachersController extends BaseController
{
    private const BACK = '/school/teachers';
    /** NULL means unlimited; otherwise the Super Admin configured active-account cap. */
    private function teacherLimit(): ?int
    {
        $value = DB::val('SELECT teacher_limit FROM schools WHERE id = ?', [$this->sid()]);
        return $value === null ? null : max(0, (int) $value);
    }


    public function index(): void
    {
        $teachers = DB::all(
            "SELECT u.id, u.name, u.email, u.status, u.last_login_at,
                    (SELECT COUNT(*) FROM teacher_assignments t WHERE t.user_id = u.id) AS assignments
             FROM users u WHERE u.school_id = ? AND u.role = 'teacher' ORDER BY u.name",
            [$this->sid()]
        );
        View::render('school/teachers', ['title' => 'Teachers', 'teachers' => $teachers, 'teacherCount' => (int) DB::val("SELECT COUNT(*) FROM users WHERE school_id = ? AND role = 'teacher' AND status = 'active'", [$this->sid()]), 'teacherLimit' => $this->teacherLimit()]);
    }

    public function create(): void
    {
        $count = (int) DB::val("SELECT COUNT(*) FROM users WHERE school_id = ? AND role = 'teacher' AND status = 'active'", [$this->sid()]);
        $limit = $this->teacherLimit();
        if ($limit !== null && $count >= $limit) { $this->fail('Your school has reached its configured limit of ' . $limit . ' active teacher accounts. Contact the GRADELY Super Administrator if you need a higher limit.', self::BACK); }
        View::render('school/teacher_new', ['title' => 'Add teacher', 'remaining' => $limit === null ? null : $limit - $count, 'teacherLimit' => $limit]);
    }

    public function store(): void
    {
        $name = $this->text('name', 150);
        $email = strtolower((string) $this->text('email'));
        $password = (string) ($_POST['password'] ?? '');
        $perms = ['scores.enter'];
        $old = ['name' => $name, 'email' => $email];
        $back = '/school/teachers/new';

        if ($name === null || $name === '') {
            $this->fail('Enter the teacher\'s name.', $back, $old);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
            $this->fail('Enter a valid email address.', $back, $old);
        }
        if ((int) DB::val('SELECT COUNT(*) FROM users WHERE email = ?', [$email]) > 0) {
            $this->fail('That email is already used by another account.', $back, $old);
        }
        $generated = $password === '';
        if ($generated) {
            $password = Passwords::generate();
        } elseif (strlen($password) < Auth::MIN_PASSWORD) {
            $this->fail('The password must be at least ' . Auth::MIN_PASSWORD . ' characters (or leave it blank to generate one).', $back, $old);
        }

        $sid = $this->sid();
        try {
        $id = DB::transaction(static function () use ($sid, $name, $email, $password, $perms): int {
            $lock = 'SELECT id FROM schools WHERE id = ?'; if (DB::isMysql()) { $lock .= ' FOR UPDATE'; }
            DB::one($lock, [$sid]);
            $limitValue = DB::val('SELECT teacher_limit FROM schools WHERE id = ?', [$sid]);
            $limit = $limitValue === null ? null : (int) $limitValue;
            if ($limit !== null && (int) DB::val("SELECT COUNT(*) FROM users WHERE school_id = ? AND role = 'teacher' AND status = 'active'", [$sid]) >= $limit) { throw new \RuntimeException('Your school has reached its configured limit of ' . $limit . ' active teacher accounts.'); }
            $uid = DB::insert(
                "INSERT INTO users (school_id, name, email, password_hash, role) VALUES (?,?,?,?, 'teacher')",
                [$sid, $name, $email, Auth::hash($password)]
            );
            foreach ($perms as $p) {
                DB::insert('INSERT INTO user_permissions (user_id, permission) VALUES (?,?)', [$uid, $p]);
            }
            return $uid;
        });
        } catch (\RuntimeException $e) { $this->fail($e->getMessage(), self::BACK); }
        AuditLog::record('teacher.created', 'user', $id, ['email' => $email]);
        $msg = "Teacher account created for $email.";
        $msg .= $generated ? " Temporary password: $password (shown once, copy it now)." : '';
        $this->ok($msg, "/school/teachers/$id/edit");
    }

    private function teacher(int $id): array
    {
        $u = $this->owned('users', $id);
        if ($u['role'] !== 'teacher') {
            Http::abort(404);
        }
        return $u;
    }

    public function edit(int $id): void
    {
        $t = $this->teacher($id);
        $sid = $this->sid();
        $pairs = DB::all(
            'SELECT cs.class_id, cs.subject_id, c.name AS class_name, s.name AS subject_name
             FROM class_subjects cs
             JOIN classes c ON c.id = cs.class_id AND c.school_id = cs.school_id
             JOIN subjects s ON s.id = cs.subject_id AND s.school_id = cs.school_id
             WHERE cs.school_id = ? ORDER BY c.level_order, c.name, s.name',
            [$sid]
        );
        $assigned = [];
        foreach (DB::all('SELECT class_id, subject_id FROM teacher_assignments WHERE user_id = ? AND school_id = ?', [$id, $sid]) as $a) {
            $assigned[$a['class_id'] . ':' . $a['subject_id']] = true;
        }
        View::render('school/teacher_edit', [
            'title' => $t['name'],
            't' => $t,
            'perms' => array_column(DB::all('SELECT permission FROM user_permissions WHERE user_id = ?', [$id]), 'permission'),
            'pairs' => $pairs,
            'assigned' => $assigned,
        ]);
    }

    public function update(int $id): void
    {
        $t = $this->teacher($id);
        $sid = $this->sid();
        $back = "/school/teachers/$id/edit";
        $name = $this->text('name', 150);
        $status = Http::input('status') === 'disabled' ? 'disabled' : 'active';
        $perms = ['scores.enter'];
        $newPw = (string) ($_POST['new_password'] ?? '');
        if ($name === null || $name === '') {
            $this->fail('Enter the teacher\'s name.', $back);
        }
        if ($newPw !== '' && strlen($newPw) < Auth::MIN_PASSWORD) {
            $this->fail('The new password must be at least ' . Auth::MIN_PASSWORD . ' characters.', $back);
        }

        // Only pairs that really exist in this school may be assigned.
        $valid = [];
        foreach (DB::all('SELECT class_id, subject_id FROM class_subjects WHERE school_id = ?', [$sid]) as $r) {
            $valid[$r['class_id'] . ':' . $r['subject_id']] = [(int) $r['class_id'], (int) $r['subject_id']];
        }
        $posted = is_array($_POST['assign'] ?? null) ? array_keys($_POST['assign']) : [];

        DB::transaction(static function () use ($id, $sid, $name, $status, $perms, $newPw, $valid, $posted): void {
            DB::run('UPDATE users SET name = ?, status = ? WHERE id = ? AND school_id = ?', [$name, $status, $id, $sid]);
            if ($newPw !== '') {
                DB::run('UPDATE users SET password_hash = ? WHERE id = ? AND school_id = ?', [Auth::hash($newPw), $id, $sid]);
            }
            DB::run('DELETE FROM user_permissions WHERE user_id = ?', [$id]);
            foreach ($perms as $p) {
                DB::insert('INSERT INTO user_permissions (user_id, permission) VALUES (?,?)', [$id, $p]);
            }
            DB::run('DELETE FROM teacher_assignments WHERE user_id = ? AND school_id = ?', [$id, $sid]);
            foreach ($posted as $key) {
                if (isset($valid[(string) $key])) {
                    DB::insert(
                        'INSERT INTO teacher_assignments (school_id, user_id, class_id, subject_id) VALUES (?,?,?,?)',
                        [$sid, $id, $valid[(string) $key][0], $valid[(string) $key][1]]
                    );
                }
            }
        });
        AuditLog::record('teacher.updated', 'user', $id, ['permissions' => $perms, 'status' => $status, 'password_changed' => $newPw !== '']);
        $this->ok($t['name'] . ' updated.', $back);
    }
}
