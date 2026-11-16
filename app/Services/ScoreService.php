<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

/** Writes score records and mirrors every change into the append-only correction history. */
final class ScoreService
{
    /**
     * @param array<int,array<int,?float>> $cells [studentId][componentId] => new score, or null to clear
     * @param 'save'|'import' $source
     * @return array{created:int,updated:int,deleted:int,unchanged:int}
     */
    public static function save(int $sid, int $termId, int $subjectId, array $cells, string $source, ?string $reason, ?int $userId): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'deleted' => 0, 'unchanged' => 0];
        DB::transaction(static function () use ($sid, $termId, $subjectId, $cells, $source, $reason, $userId, &$stats): void {
            foreach ($cells as $studentId => $byComponent) {
                foreach ($byComponent as $componentId => $value) {
                    $row = DB::one(
                        'SELECT id, score FROM scores WHERE school_id = ? AND term_id = ? AND student_id = ? AND subject_id = ? AND component_id = ?',
                        [$sid, $termId, $studentId, $subjectId, $componentId]
                    );
                    $hist = static function (?float $old, ?float $new, string $action) use ($sid, $termId, $studentId, $subjectId, $componentId, $reason, $userId): void {
                        DB::insert(
                            'INSERT INTO score_history (school_id, term_id, student_id, subject_id, component_id, old_score, new_score, action, reason, changed_by) VALUES (?,?,?,?,?,?,?,?,?,?)',
                            [$sid, $termId, $studentId, $subjectId, $componentId, $old, $new, $action, $reason, $userId]
                        );
                    };
                    if ($value === null) {
                        if ($row !== null) {
                            DB::run('DELETE FROM scores WHERE id = ? AND school_id = ?', [$row['id'], $sid]);
                            $hist((float) $row['score'], null, 'delete');
                            $stats['deleted']++;
                        }
                        continue;
                    }
                    if ($row === null) {
                        DB::insert(
                            'INSERT INTO scores (school_id, term_id, student_id, subject_id, component_id, score, entered_by) VALUES (?,?,?,?,?,?,?)',
                            [$sid, $termId, $studentId, $subjectId, $componentId, $value, $userId]
                        );
                        $hist(null, $value, $source === 'import' ? 'import' : 'create');
                        $stats['created']++;
                    } elseif (abs((float) $row['score'] - $value) > 0.0001) {
                        DB::run(
                            'UPDATE scores SET score = ?, entered_by = ?, updated_at = ? WHERE id = ? AND school_id = ?',
                            [$value, $userId, date('Y-m-d H:i:s'), $row['id'], $sid]
                        );
                        $hist((float) $row['score'], $value, $source === 'import' ? 'import' : 'update');
                        $stats['updated']++;
                    } else {
                        $stats['unchanged']++;
                    }
                }
            }
        });
        return $stats;
    }
}
