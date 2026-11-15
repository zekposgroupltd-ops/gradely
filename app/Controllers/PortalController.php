<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{DB, Http, RateLimiter, Session, View};
use App\Services\{ReportService, ScratchCardService};

/** Public student/parent result portal. Never trusts the browser: the backend validates every request. */
final class PortalController
{
    private const WINDOW = 900;
    private const VIEW_SECONDS = 1800;

    private function school(string $slug): ?array
    {
        return DB::one("SELECT id, name, slug, logo_path FROM schools WHERE slug = ? AND status = 'active'", [$slug]);
    }

    private function terms(int $sid): array
    {
        return DB::all(
            'SELECT t.id, t.name, s.name AS session_name FROM terms t JOIN academic_sessions s ON s.id = t.session_id AND s.school_id = t.school_id
             WHERE t.school_id = ? ORDER BY s.name DESC, t.id DESC',
            [$sid]
        );
    }

    public function home(): void
    {
        $slug = strtolower(Http::input('school'));
        $this->render($slug !== '' ? $this->school($slug) : null, $slug !== '');
    }

    public function forSchool(string $slug): void
    {
        $this->render($this->school(strtolower($slug)), true);
    }

    private function render(?array $school, bool $asked): void
    {
        if ($asked && $school === null) {
            http_response_code(404);
        }
        $schools = $school === null ? DB::all("SELECT name, slug FROM schools WHERE status = 'active' ORDER BY name") : [];
        View::render('portal/home', [
            'title' => 'Check your result', 'school' => $school, 'schools' => $schools,
            'terms' => $school ? $this->terms((int) $school['id']) : [],
            'notFound' => $asked && $school === null,
        ], 'portal');
    }

    private function back(string $slug, string $msg): never
    {
        Session::flash('error', $msg);
        Session::flashOld(['admission_no' => Http::input('admission_no'), 'term_id' => Http::input('term_id')]);
        Http::redirect('/s/' . rawurlencode($slug));
    }

    public function check(): void
    {
        $slug = strtolower(Http::input('school'));
        $school = $this->school($slug);
        if ($school === null) {
            Session::flash('error', 'Select your school first.');
            Http::redirect('/');
        }
        $sid = (int) $school['id'];
        $adm = Http::input('admission_no');
        $pin = (string) ($_POST['pin'] ?? '');
        $termId = (int) Http::input('term_id');

        $keys = ['portal:ip:' . Http::ip() => 10, 'portal:stu:' . $sid . ':' . strtolower($adm) => 8, 'portal:school:' . $sid => 400];
        foreach ($keys as $k => $max) {
            if (RateLimiter::tooMany($k, $max, $k === 'portal:school:' . $sid ? 3600 : self::WINDOW)) {
                $this->back($slug, 'Too many unsuccessful attempts. Please wait a while and try again.');
            }
        }
        if ($adm === '' || strlen($adm) > 40 || $termId <= 0) {
            $this->back($slug, 'Enter your student ID and choose the term.');
        }
        if (DB::val('SELECT COUNT(*) FROM terms WHERE id = ? AND school_id = ?', [$termId, $sid]) == 0) {
            $this->back($slug, 'Choose a valid term.');
        }

        $res = ScratchCardService::redeem($sid, $termId, $adm, $pin, Http::ip(), (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
        if (!$res['ok']) {
            if ($res['code'] !== 'not_published') {
                foreach ($keys as $k => $max) {
                    RateLimiter::hit($k, $k === 'portal:school:' . $sid ? 3600 : self::WINDOW);
                }
            }
            $this->back($slug, (string) $res['error']);
        }

        Session::regenerate();
        $_SESSION['portal'] = [
            'school_id' => $sid, 'term_id' => $termId, 'student_id' => (int) $res['student_id'],
            'remaining' => (int) $res['remaining'], 'until' => time() + self::VIEW_SECONDS, 'slug' => $slug,
        ];
        Http::redirect('/portal/result');
    }

    /** Re-displays the result for the current browsing session without consuming another check. */
    public function result(): void
    {
        $p = $_SESSION['portal'] ?? null;
        if (!is_array($p) || (int) $p['until'] < time()) {
            unset($_SESSION['portal']);
            Session::flash('error', 'Your result session has ended. Enter your card details again to view the result.');
            Http::redirect('/');
        }
        $report = ReportService::build((int) $p['school_id'], (int) $p['term_id'], (int) $p['student_id'], true);
        if ($report === null) {
            unset($_SESSION['portal']);
            Session::flash('error', 'This result is no longer available.');
            Http::redirect('/');
        }
        View::render('report/page', [
            'title' => 'Result - ' . $report['student']['first_name'] . ' ' . $report['student']['last_name'],
            'reports' => [$report], 'portal' => true, 'remaining' => (int) $p['remaining'], 'back' => '/s/' . rawurlencode((string) $p['slug']),
        ], 'report');
    }

    public function finish(): void
    {
        $slug = $_SESSION['portal']['slug'] ?? '';
        unset($_SESSION['portal']);
        Http::redirect($slug !== '' ? '/s/' . rawurlencode((string) $slug) : '/');
    }
}
