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
        // The public landing page deliberately does not enumerate schools. The PIN identifies the school.
        $schools = [];
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
        Http::redirect($slug !== '' ? '/s/' . rawurlencode($slug) : '/');
    }

    public function check(): void
    {
        $slug = strtolower(Http::input('school'));
        $publicLookup = $slug === '';
        $backSlug = $publicLookup ? '' : $slug;
        $adm = trim(Http::input('admission_no'));
        $pin = (string) ($_POST['pin'] ?? '');
        if ($adm === '' || strlen($adm) > 40 || $pin === '') {
            $this->back($backSlug, 'Enter your student ID and result-checking PIN.');
        }
        $ipKey = 'portal:ip:' . Http::ip();
        if (RateLimiter::tooMany($ipKey, 10, self::WINDOW)) {
            $this->back($backSlug, 'Too many unsuccessful attempts. Please wait a while and try again.');
        }

        if ($slug === '') {
            // PIN hash identifies the issuing school and term, so students never need to select a school.
            $card = DB::one(
                "SELECT c.school_id, c.term_id, s.slug FROM scratch_cards c JOIN schools s ON s.id = c.school_id AND s.status = 'active' WHERE c.pin_hash = ?",
                [ScratchCardService::hashPin($pin)]
            );
            if ($card === null) {
                RateLimiter::hit($ipKey, self::WINDOW);
                $this->back('', 'We could not verify those details. Check your student ID and PIN, then try again.');
            }
            $sid = (int) $card['school_id'];
            $termId = (int) $card['term_id'];
            $slug = (string) $card['slug'];
        } else {
            $school = $this->school($slug);
            if ($school === null) {
                RateLimiter::hit($ipKey, self::WINDOW);
                $this->back($backSlug, 'That school portal is not available.');
            }
            $sid = (int) $school['id'];
            $termId = (int) Http::input('term_id');
            if ($termId <= 0 || DB::val('SELECT COUNT(*) FROM terms WHERE id = ? AND school_id = ?', [$termId, $sid]) == 0) {
                $this->back($backSlug, 'Choose a valid session and term.');
            }
        }

        $studentKey = 'portal:stu:' . $sid . ':' . strtolower($adm);
        $schoolKey = 'portal:school:' . $sid;
        if (RateLimiter::tooMany($studentKey, 8, self::WINDOW) || RateLimiter::tooMany($schoolKey, 400, 3600)) {
            $this->back($backSlug, 'Too many unsuccessful attempts. Please wait a while and try again.');
        }

        $res = ScratchCardService::redeem($sid, $termId, $adm, $pin, Http::ip(), (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
        if (!$res['ok']) {
            if ($res['code'] !== 'not_published') {
                RateLimiter::hit($ipKey, self::WINDOW);
                RateLimiter::hit($studentKey, self::WINDOW);
                RateLimiter::hit($schoolKey, 3600);
            }
            $this->back($backSlug, (string) $res['error']);
        }

        Session::regenerate();
        $_SESSION['portal'] = [
            'school_id' => $sid, 'term_id' => $termId, 'student_id' => (int) $res['student_id'],
            'remaining' => (int) $res['remaining'], 'until' => time() + self::VIEW_SECONDS, 'slug' => $slug,
            'public_lookup' => $publicLookup,
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
            'reports' => [$report], 'portal' => true, 'remaining' => (int) $p['remaining'], 'back' => !empty($p['public_lookup']) ? '/' : '/s/' . rawurlencode((string) $p['slug']),
        ], 'report');
    }

    public function finish(): void
    {
        $slug = $_SESSION['portal']['slug'] ?? '';
        $public = !empty($_SESSION['portal']['public_lookup']);
        unset($_SESSION['portal']);
        Http::redirect($public || $slug === '' ? '/' : '/s/' . rawurlencode((string) $slug));
    }
}
