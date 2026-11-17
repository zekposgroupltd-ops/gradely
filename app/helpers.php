<?php
declare(strict_types=1);

use App\Core\{Csrf, Env, Session};

/** HTML-escape for output. Use on EVERY dynamic value in views. */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    $base = rtrim((string) Env::get('APP_BASE_PATH', ''), '/');
    return $base . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/'));
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(Csrf::token()) . '">';
}

function old(string $key, string $default = ''): string
{
    return Session::old($key, $default);
}

/** Inline SVG icon set (stroke icons, 24px grid). */
function icon(string $name, int $size = 20): string
{
    static $paths = [
        'dashboard' => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/>',
        'bell' => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>',
        'school' => '<path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M9 21v-6h6v6"/>',
        'users' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c0-3.6 2.9-6 6.5-6s6.5 2.4 6.5 6"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7"/><path d="M18 14.3c2 .7 3.5 2.5 3.5 5.7"/>',
        'card' => '<rect x="2.5" y="5" width="19" height="14" rx="2.5"/><path d="M2.5 10h19"/>',
        'book' => '<path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v16H6.5A2.5 2.5 0 0 0 4 21.5z"/><path d="M4 5.5v16"/>',
        'layers' => '<path d="M12 3l9 5-9 5-9-5z"/><path d="M3 13l9 5 9-5"/>',
        'check' => '<path d="M5 12.5l4.5 4.5L19 7.5"/>',
        'download' => '<path d="M12 3v12"/><path d="M7 10l5 5 5-5"/><path d="M4 20h16"/>',
        'upload' => '<path d="M12 16V4"/><path d="M7 9l5-5 5 5"/><path d="M4 20h16"/>',
        'calendar' => '<rect x="3.5" y="5" width="17" height="15.5" rx="2.5"/><path d="M3.5 10h17"/><path d="M8 3v4"/><path d="M16 3v4"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-6.5 8-6.5s8 2.5 8 6.5"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M12 2.8v2.5M12 18.7v2.5M4.2 7.4l2.2 1.2M17.6 15.4l2.2 1.2M4.2 16.6l2.2-1.2M17.6 8.6l2.2-1.2"/>',
        'edit' => '<path d="M4 20h4L19 9a2.1 2.1 0 0 0-4-4L4 16z"/><path d="M13.5 6.5l4 4"/>',
        'chart' => '<path d="M4 20V10"/><path d="M10 20V4"/><path d="M16 20v-7"/><path d="M22 20H2"/>',
        'print' => '<path d="M7 9V3h10v6"/><rect x="3" y="9" width="18" height="8" rx="2"/><path d="M7 14h10v7H7z"/>',
        'plus' => '<path d="M12 5v14"/><path d="M5 12h14"/>',
        'shield' => '<path d="M12 3l8 3v6c0 4.5-3.2 8-8 9-4.8-1-8-4.5-8-9V6z"/><path d="M9 12l2.2 2.2L15.5 10"/>',
    ];
    $inner = $paths[$name] ?? '';
    return '<svg class="icon" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $inner . '</svg>';
}

/** Labelled text input. $attrs values are escaped; boolean true renders a bare attribute. */
function field_html(string $label, string $name, string $value = '', string $type = 'text', array $attrs = [], string $hint = ''): string
{
    $a = '';
    foreach ($attrs as $k => $v) {
        $a .= $v === true ? ' ' . e($k) : ' ' . e($k) . '="' . e($v) . '"';
    }
    return '<label class="field"><span class="field-label">' . e($label)
        . ($hint !== '' ? ' <span class="hint">' . e($hint) . '</span>' : '')
        . '</span><input type="' . e($type) . '" name="' . e($name) . '" value="' . e($value) . '"' . $a . '></label>';
}

/** Labelled select. $options is value => label. */
function select_html(string $label, string $name, array $options, string $selected = '', array $attrs = [], ?string $blank = null): string
{
    $a = '';
    foreach ($attrs as $k => $v) {
        $a .= $v === true ? ' ' . e($k) : ' ' . e($k) . '="' . e($v) . '"';
    }
    $o = $blank !== null ? '<option value="">' . e($blank) . '</option>' : '';
    foreach ($options as $v => $l) {
        $o .= '<option value="' . e($v) . '"' . ((string) $v === $selected ? ' selected' : '') . '>' . e($l) . '</option>';
    }
    return '<label class="field"><span class="field-label">' . e($label) . '</span><select name="' . e($name) . '"' . $a . '>' . $o . '</select></label>';
}

/** Neutralise spreadsheet formula injection in exported cells. */
function csv_safe(mixed $v): string
{
    $s = (string) $v;
    return $s !== '' && in_array($s[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'" . $s : $s;
}

/** Number without trailing zeros: 12.50 → 12.5, 40.00 → 40. */
function num(float|int|string|null $v, int $dp = 2): string
{
    if ($v === null || $v === '') {
        return '—';
    }
    return rtrim(rtrim(number_format((float) $v, $dp, '.', ''), '0'), '.') ?: '0';
}

function badge_html(string $text, string $color = 'gray'): string
{
    return '<span class="badge badge-' . e($color) . '">' . e($text) . '</span>';
}

/** Status word → coloured badge (results, cards, schools). */
function status_badge(string $status): string
{
    $map = [
        'active' => 'green', 'published' => 'green', 'approved' => 'blue', 'fulfilled' => 'green', 'pending' => 'amber', 'rejected' => 'red', 'draft' => 'amber', 'none' => 'gray',
        'exhausted' => 'gray', 'revoked' => 'red', 'expired' => 'amber', 'suspended' => 'amber', 'inactive' => 'gray',
        'disabled' => 'red', 'withdrawn' => 'gray', 'graduated' => 'blue', 'mixed' => 'amber',
    ];
    return badge_html(ucfirst($status), $map[$status] ?? 'gray');
}

/** A tiny SVG progress bar (attributes only, so it works under a strict CSP). */
function bar_html(float $pct, string $label = ''): string
{
    $p = max(0.0, min(100.0, $pct));
    return '<svg class="bar" viewBox="0 0 100 8" preserveAspectRatio="none" role="img" aria-label="' . e($label !== '' ? $label : num($p, 1) . '%') . '">'
        . '<rect class="bar-bg" width="100" height="8" rx="4"/><rect class="bar-fg" width="' . e(round($p, 2)) . '" height="8" rx="4"/></svg>';
}

/** Term <option> labels: "2026/2027 · First Term". */
function term_options(array $terms): array
{
    $o = [];
    foreach ($terms as $t) {
        $o[$t['id']] = $t['session_name'] . ' · ' . $t['name'] . ((int) ($t['is_current'] ?? 0) === 1 ? ' (current)' : '');
    }
    return $o;
}

/** Page links for the current path, keeping the other query parameters. */
function pager_html(int $page, int $pages, array $query = []): string
{
    if ($pages <= 1) {
        return '';
    }
    $link = static function (int $p, string $label, bool $disabled = false, bool $current = false) use ($query): string {
        if ($disabled) {
            return '<span class="pager-item is-disabled">' . e($label) . '</span>';
        }
        $q = http_build_query(array_filter($query + ['page' => $p], static fn($v) => $v !== '' && $v !== null && $v !== 0));
        $href = url(\App\Core\Http::path()) . '?' . $q;
        return '<a class="pager-item' . ($current ? ' is-current' : '') . '" href="' . e($href) . '"' . ($current ? ' aria-current="page"' : '') . '>' . e($label) . '</a>';
    };
    $out = '<nav class="pager" aria-label="Pagination">' . $link($page - 1, '‹ Prev', $page <= 1);
    for ($p = max(1, $page - 2); $p <= min($pages, $page + 2); $p++) {
        $out .= $link($p, (string) $p, false, $p === $page);
    }
    return $out . $link($page + 1, 'Next ›', $page >= $pages) . '</nav>';
}

function unread_count(): int
{
    $u = \App\Core\Auth::user();
    return $u === null ? 0 : (int) \App\Core\DB::val('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL', [$u['id']]);
}

/** Renders and clears queued flash messages. */
function flash_html(): string
{
    $out = '';
    foreach (Session::pullFlash() as $f) {
        $t = in_array($f['type'], ['error', 'success', 'info'], true) ? $f['type'] : 'info';
        $out .= '<div class="alert alert-' . $t . '" role="' . ($t === 'error' ? 'alert' : 'status') . '">' . e($f['message']) . '</div>';
    }
    return $out;
}

function role_label(?string $role): string
{
    return match ($role) {
        'super_admin' => 'Super Administrator',
        'school_admin' => 'School Administrator',
        'teacher' => 'Teacher',
        default => '',
    };
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $out = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        $out .= mb_strtoupper(mb_substr($p, 0, 1));
    }
    return $out !== '' ? $out : '?';
}
