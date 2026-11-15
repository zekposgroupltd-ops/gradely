<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{AuditLog, DB, View};

/** Classes and their arms (e.g. JSS 1 → A, B, C). */
final class ClassesController extends BaseController
{
    private const BACK = '/school/classes';

    public function index(): void
    {
        $sid = $this->sid();
        $classes = DB::all(
            'SELECT c.*, (SELECT COUNT(*) FROM students s WHERE s.class_id = c.id AND s.school_id = c.school_id) AS students
             FROM classes c WHERE c.school_id = ? ORDER BY c.level_order, c.name',
            [$sid]
        );
        $arms = [];
        foreach (DB::all('SELECT * FROM class_arms WHERE school_id = ? ORDER BY name', [$sid]) as $a) {
            $arms[$a['class_id']][] = $a;
        }
        View::render('school/classes', ['title' => 'Classes & arms', 'classes' => $classes, 'arms' => $arms]);
    }

    public function store(): void
    {
        $name = $this->text('name', 60);
        if ($name === null || $name === '') {
            $this->fail('Enter a class name such as JSS 1.', self::BACK);
        }
        $sid = $this->sid();
        if ((int) DB::val('SELECT COUNT(*) FROM classes WHERE school_id = ? AND name = ?', [$sid, $name]) > 0) {
            $this->fail('That class already exists.', self::BACK);
        }
        $order = (int) DB::val('SELECT COALESCE(MAX(level_order), 0) FROM classes WHERE school_id = ?', [$sid]) + 1;
        $id = DB::insert('INSERT INTO classes (school_id, name, level_order) VALUES (?,?,?)', [$sid, $name, $order]);
        AuditLog::record('class.created', 'class', $id, ['name' => $name]);
        $this->ok("Class $name added.", self::BACK);
    }

    public function delete(int $id): void
    {
        $this->owned('classes', $id);
        $sid = $this->sid();
        $this->tryDelete(
            static fn() => DB::transaction(static function () use ($id, $sid): void {
                DB::run('DELETE FROM teacher_assignments WHERE class_id = ? AND school_id = ?', [$id, $sid]);
                DB::run('DELETE FROM class_subjects WHERE class_id = ? AND school_id = ?', [$id, $sid]);
                DB::run('DELETE FROM class_arms WHERE class_id = ? AND school_id = ?', [$id, $sid]);
                DB::run('DELETE FROM classes WHERE id = ? AND school_id = ?', [$id, $sid]);
            }),
            'Class deleted.',
            'This class has students or results, so it cannot be deleted. Move the students first.',
            self::BACK
        );
    }

    public function storeArm(int $id): void
    {
        $class = $this->owned('classes', $id);
        $name = $this->text('name', 40);
        if ($name === null || $name === '') {
            $this->fail('Enter an arm name such as A.', self::BACK);
        }
        if ((int) DB::val('SELECT COUNT(*) FROM class_arms WHERE class_id = ? AND name = ?', [$id, $name]) > 0) {
            $this->fail('That arm already exists in ' . $class['name'] . '.', self::BACK);
        }
        DB::insert('INSERT INTO class_arms (school_id, class_id, name) VALUES (?,?,?)', [$this->sid(), $id, $name]);
        AuditLog::record('arm.created', 'class', $id, ['arm' => $name]);
        $this->ok("Arm $name added to {$class['name']}.", self::BACK);
    }

    public function deleteArm(int $id): void
    {
        $this->owned('class_arms', $id);
        $this->tryDelete(
            fn() => DB::run('DELETE FROM class_arms WHERE id = ? AND school_id = ?', [$id, $this->sid()]),
            'Arm deleted.',
            'Students are assigned to this arm, so it cannot be deleted.',
            self::BACK
        );
    }
}
