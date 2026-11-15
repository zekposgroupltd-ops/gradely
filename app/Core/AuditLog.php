<?php
declare(strict_types=1);

namespace App\Core;

final class AuditLog
{
    /**
     * @param array<string,mixed> $meta never put passwords, PINs or tokens in here
     */
    public static function record(string $action, ?string $entity = null, string|int|null $entityId = null, array $meta = [], ?int $schoolId = null, ?int $userId = null): void
    {
        try {
            $u = Auth::user();
            DB::run(
                'INSERT INTO audit_logs (school_id, user_id, action, entity, entity_id, meta, ip) VALUES (?,?,?,?,?,?,?)',
                [
                    $schoolId ?? ($u['school_id'] ?? null),
                    $userId ?? ($u['id'] ?? null),
                    $action,
                    $entity,
                    $entityId === null ? null : (string) $entityId,
                    $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
                    Http::ip(),
                ]
            );
        } catch (\Throwable $e) {
            error_log('Audit log failure: ' . $e->getMessage()); // never break the request for logging
        }
    }
}
