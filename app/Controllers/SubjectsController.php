<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{AuditLog, DB, View};

/** Subjects and their assignment to classes. */
final class SubjectsController extends BaseController
{
    private const BACK = '/school/subjects';

    public function index(): void
    {
        $sid = $this->sid();
        $assigned = [];
        foreach (DB::all('SELECT class_id, subject_id FROM class_subjects WHERE school_id = ?', [$sid]) as $r) {
            $assigned[$r['subject_id'] . ':' . $r['class_id']] = true;
        }
        View::render('school/subjects', [
            'title' => 'Subjects',
            'subjects' => DB::all('SELECT * FROM subjects WHERE school_id = ? ORDER BY name', [$sid]),
            'classes' => DB::all('SELECT id, name FROM classes WHERE school_id = ? ORDER BY level_order, name', [$sid]),
            'assigned' => $assigned,
        ]);
    }

    public function store(): void
    {
        $name = $this->text('name', 100);
        $code = $this->text('code', 20);
        if ($name === null || $name === '' || $code === null) {
            $this->fail('Enter a subject name (up to 100 characters) and an optional short code.', self::BACK);
        }
        if ((int) DB::val('SELECT COUNT(*) FROM subjects WHERE school_id = ? AND name = ?', [$this->sid(), $name]) > 0) {
            $this->fail('That subject already exists.', self::BACK);
        }
        $id = DB::insert('INSERT INTO subjects (school_id, name, code) VALUES (?,?,?)', [$this->sid(), $name, $code ?: null]);
        AuditLog::record('subject.created', 'subject', $id, ['name' => $name]);
        $this->ok("Subject $name added. Assign it to classes below.", self::BACK);
    }

    public function delete(int $id): void
    {
        $this->owned('subjects', $id);
        $sid = $this->sid();
        $this->tryDelete(
            static fn() => DB::transaction(static function () use ($id, $sid): void {
                DB::run('DELETE FROM teacher_assignments WHERE subject_id = ? AND school_id = ?', [$id, $sid]);
                DB::run('DELETE FROM class_subjects WHERE subject_id = ? AND school_id = ?', [$id, $sid]);
                DB::run('DELETE FROM subjects WHERE id = ? AND school_id = ?', [$id, $sid]);
            }),
            'Subject deleted.',
            'This subject already has scores, so it cannot be deleted.',
            self::BACK
        );
    }

    /** Saves the whole subject × class matrix. Every id is verified to belong to this school. */
    public function assign(): void
    {
        $sid = $this->sid();
        $classIds = array_map('intval', array_column(DB::all('SELECT id FROM classes WHERE school_id = ?', [$sid]), 'id'));
        $subjectIds = array_map('intval', array_column(DB::all('SELECT id FROM subjects WHERE school_id = ?', [$sid]), 'id'));
        $posted = is_array($_POST['assign'] ?? null) ? $_POST['assign'] : [];

        $pairs = [];
        foreach ($posted as $subjectId => $classes) {
            if (!in_array((int) $subjectId, $subjectIds, true) || !is_array($classes)) {
                continue;
            }
            foreach (array_keys($classes) as $classId) {
                if (in_array((int) $classId, $classIds, true)) {
                    $pairs[] = [(int) $classId, (int) $subjectId];
                }
            }
        }

        DB::transaction(static function () use ($sid, $pairs): void {
            $existing = [];
            foreach (DB::all('SELECT class_id, subject_id FROM class_subjects WHERE school_id = ?', [$sid]) as $r) {
                $existing[$r['class_id'] . ':' . $r['subject_id']] = true;
            }
            $wanted = [];
            foreach ($pairs as [$c, $s]) {
                $wanted[$c . ':' . $s] = true;
                if (!isset($existing[$c . ':' . $s])) {
                    DB::insert('INSERT INTO class_subjects (school_id, class_id, subject_id) VALUES (?,?,?)', [$sid, $c, $s]);
                }
            }
            foreach (array_keys($existing) as $key) {
                if (!isset($wanted[$key])) {
                    [$c, $s] = array_map('intval', explode(':', $key));
                    DB::run('DELETE FROM teacher_assignments WHERE school_id = ? AND class_id = ? AND subject_id = ?', [$sid, $c, $s]);
                    DB::run('DELETE FROM class_subjects WHERE school_id = ? AND class_id = ? AND subject_id = ?', [$sid, $c, $s]);
                }
            }
        });
        AuditLog::record('subjects.assigned', 'class_subjects', null, ['pairs' => count($pairs)]);
        $this->ok('Subject assignments saved.', self::BACK);
    }
}
