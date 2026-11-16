<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Auth, DB, Http, Session};

abstract class BaseController
{
    /** Tables a controller may look up by id with tenant scoping. */
    private const OWNED = ['students', 'classes', 'class_arms', 'subjects', 'academic_sessions', 'terms', 'users'];

    /** The tenant. Always derived from the authenticated session. */
    protected function sid(): int
    {
        return Auth::schoolId();
    }

    /** A record of the CURRENT school, or a 404 - the only way controllers load a row by id. */
    protected function owned(string $table, int $id): array
    {
        if (!in_array($table, self::OWNED, true)) {
            throw new \LogicException("Table $table is not tenant-lookup enabled.");
        }
        $row = DB::one("SELECT * FROM $table WHERE id = ? AND school_id = ?", [$id, $this->sid()]);
        if ($row === null) {
            Http::abort(404);
        }
        return $row;
    }

    protected function ok(string $message, string $to): never
    {
        Session::flash('success', $message);
        Http::redirect($to);
    }

    protected function fail(string $message, string $to, array $old = []): never
    {
        Session::flash('error', $message);
        if ($old) {
            Session::flashOld($old);
        }
        Http::redirect($to);
    }

    /** Trimmed single-line text, limited in length. Returns null when it exceeds $max. */
    protected function text(string $key, int $max = 190): ?string
    {
        $v = preg_replace('/\s+/u', ' ', Http::input($key)) ?? '';
        return mb_strlen($v) > $max ? null : $v;
    }

    /** Runs a delete; foreign-key refusals become a friendly flash instead of a 500. */
    protected function tryDelete(callable $fn, string $okMsg, string $inUseMsg, string $to): never
    {
        try {
            $fn();
        } catch (\PDOException $e) {
            if (str_starts_with((string) $e->getCode(), '23')) {
                $this->fail($inUseMsg, $to);
            }
            throw $e;
        }
        $this->ok($okMsg, $to);
    }

    protected static function isDuplicate(\PDOException $e): bool
    {
        return str_starts_with((string) $e->getCode(), '23');
    }
}
