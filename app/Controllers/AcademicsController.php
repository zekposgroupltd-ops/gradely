<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{AuditLog, DB, Http, View};

/** Academic sessions and terms. */
final class AcademicsController extends BaseController
{
    private const BACK = '/school/academics';

    public function index(): void
    {
        $sid = $this->sid();
        $sessions = DB::all('SELECT * FROM academic_sessions WHERE school_id = ? ORDER BY name DESC', [$sid]);
        $terms = [];
        foreach (DB::all('SELECT * FROM terms WHERE school_id = ? ORDER BY id', [$sid]) as $t) {
            $terms[$t['session_id']][] = $t;
        }
        View::render('school/academics', ['title' => 'Sessions & terms', 'sessions' => $sessions, 'terms' => $terms]);
    }

    public function storeSession(): void
    {
        $name = $this->text('name', 40);
        if ($name === null || mb_strlen($name) < 3) {
            $this->fail('Enter a session name such as 2026/2027.', self::BACK);
        }
        if ((int) DB::val('SELECT COUNT(*) FROM academic_sessions WHERE school_id = ? AND name = ?', [$this->sid(), $name]) > 0) {
            $this->fail('That session already exists.', self::BACK);
        }
        $id = DB::insert('INSERT INTO academic_sessions (school_id, name) VALUES (?, ?)', [$this->sid(), $name]);
        AuditLog::record('session.created', 'session', $id, ['name' => $name]);
        $this->ok("Session $name added. Now add its terms.", self::BACK);
    }

    public function deleteSession(int $id): void
    {
        $s = $this->owned('academic_sessions', $id);
        $this->tryDelete(
            fn() => DB::run('DELETE FROM academic_sessions WHERE id = ? AND school_id = ?', [$id, $this->sid()]),
            'Session deleted.',
            'This session still has terms or records, so it cannot be deleted.',
            self::BACK
        );
    }

    public function storeTerm(): void
    {
        $session = $this->owned('academic_sessions', (int) Http::input('session_id'));
        $name = $this->text('name', 40);
        $next = Http::input('next_term_begins');
        if ($name === null || $name === '') {
            $this->fail('Enter a term name such as First Term.', self::BACK);
        }
        if ($next !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $next)) {
            $this->fail('The next-term date must be a valid date.', self::BACK);
        }
        if ((int) DB::val('SELECT COUNT(*) FROM terms WHERE session_id = ? AND name = ?', [$session['id'], $name]) > 0) {
            $this->fail('That term already exists in this session.', self::BACK);
        }
        $id = DB::insert(
            'INSERT INTO terms (school_id, session_id, name, next_term_begins) VALUES (?,?,?,?)',
            [$this->sid(), $session['id'], $name, $next ?: null]
        );
        $hasCurrent = (int) DB::val('SELECT COUNT(*) FROM terms WHERE school_id = ? AND is_current = 1', [$this->sid()]);
        if ($hasCurrent === 0) {
            $this->makeCurrent($id, (int) $session['id']);
        }
        AuditLog::record('term.created', 'term', $id, ['name' => $name, 'session' => $session['name']]);
        $this->ok("$name added to {$session['name']}.", self::BACK);
    }

    public function setCurrent(int $id): void
    {
        $t = $this->owned('terms', $id);
        $this->makeCurrent($id, (int) $t['session_id']);
        AuditLog::record('term.set_current', 'term', $id);
        $this->ok($t['name'] . ' is now the current term.', self::BACK);
    }

    private function makeCurrent(int $termId, int $sessionId): void
    {
        $sid = $this->sid();
        DB::transaction(static function () use ($sid, $termId, $sessionId): void {
            DB::run('UPDATE terms SET is_current = 0 WHERE school_id = ?', [$sid]);
            DB::run('UPDATE academic_sessions SET is_current = 0 WHERE school_id = ?', [$sid]);
            DB::run('UPDATE terms SET is_current = 1 WHERE id = ? AND school_id = ?', [$termId, $sid]);
            DB::run('UPDATE academic_sessions SET is_current = 1 WHERE id = ? AND school_id = ?', [$sessionId, $sid]);
        });
    }

    public function updateTerm(int $id): void
    {
        $this->owned('terms', $id);
        $next = Http::input('next_term_begins');
        if ($next !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $next)) {
            $this->fail('The next-term date must be a valid date.', self::BACK);
        }
        DB::run('UPDATE terms SET next_term_begins = ? WHERE id = ? AND school_id = ?', [$next ?: null, $id, $this->sid()]);
        $this->ok('Term updated.', self::BACK);
    }

    public function deleteTerm(int $id): void
    {
        $this->owned('terms', $id);
        $this->tryDelete(
            fn() => DB::run('DELETE FROM terms WHERE id = ? AND school_id = ?', [$id, $this->sid()]),
            'Term deleted.',
            'This term already has scores, results or scratch cards, so it cannot be deleted.',
            self::BACK
        );
    }
}
