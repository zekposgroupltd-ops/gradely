<?php
declare(strict_types=1);
/**
 * Safe, additive GRADELY upgrade. It never drops tables, resets users, or changes passwords.
 * Run once after deploying this code: php tools/upgrade_2026_11_17.php
 */
define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/bootstrap.php';

use App\Core\DB;

if (!DB::isMysql()) { fwrite(STDERR, "This production upgrade requires MySQL/MariaDB.\n"); exit(2); }
$pdo = DB::pdo();
$schema = (string) DB::val('SELECT DATABASE()');
if ($schema === '') { throw new RuntimeException('No database is selected.'); }
$hasColumn = static function (string $table, string $column) use ($schema): bool {
    return (int) DB::val('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ?', [$schema, $table, $column]) > 0;
};
$hasTable = static function (string $table) use ($schema): bool {
    return (int) DB::val('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?', [$schema, $table]) > 0;
};
$steps = [];
if (!$hasTable('schools') || !$hasTable('scratch_card_batches') || !$hasTable('users') || !$hasTable('terms') || !$hasTable('classes')) {
    throw new RuntimeException('The existing GRADELY base schema was not found. Import/install the original schema first; do not run this upgrade on an empty database.');
}
if (!$hasColumn('schools', 'admission_prefix')) {
    $pdo->exec("ALTER TABLE schools ADD COLUMN admission_prefix VARCHAR(12) NULL AFTER logo_path");
    $steps[] = 'Added schools.admission_prefix';
}
if (!$hasColumn('scratch_card_batches', 'class_id')) {
    $pdo->exec("ALTER TABLE scratch_card_batches ADD COLUMN class_id INT UNSIGNED NULL AFTER term_id");
    $steps[] = 'Added scratch_card_batches.class_id';
}
if (!$hasColumn('scratch_card_batches', 'request_id')) {
    $pdo->exec("ALTER TABLE scratch_card_batches ADD COLUMN request_id BIGINT UNSIGNED NULL AFTER class_id");
    $steps[] = 'Added scratch_card_batches.request_id';
}
if (!$hasTable('pin_requests')) {
    $pdo->exec("CREATE TABLE pin_requests (
      id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      school_id INT UNSIGNED NOT NULL,
      term_id INT UNSIGNED NOT NULL,
      class_id INT UNSIGNED NULL,
      quantity INT UNSIGNED NOT NULL,
      unit_price DECIMAL(10,2) NOT NULL,
      total_amount DECIMAL(12,2) NOT NULL,
      status ENUM('pending','approved','rejected','fulfilled') NOT NULL DEFAULT 'pending',
      requested_by INT UNSIGNED NULL,
      reviewed_by INT UNSIGNED NULL,
      review_note VARCHAR(255) NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      reviewed_at DATETIME NULL,
      fulfilled_at DATETIME NULL,
      UNIQUE KEY uq_pinreq_id_school (id, school_id),
      KEY ix_pinreq_school_status (school_id, status, created_at),
      KEY ix_pinreq_term_class (school_id, term_id, class_id),
      CONSTRAINT fk_pinreq_school FOREIGN KEY (school_id) REFERENCES schools(id),
      CONSTRAINT fk_pinreq_term FOREIGN KEY (term_id, school_id) REFERENCES terms(id, school_id),
      CONSTRAINT fk_pinreq_class FOREIGN KEY (class_id, school_id) REFERENCES classes(id, school_id),
      CONSTRAINT fk_pinreq_requester FOREIGN KEY (requested_by, school_id) REFERENCES users(id, school_id),
      CONSTRAINT fk_pinreq_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $steps[] = 'Created pin_requests';
}
// Add optional request relationship only after pin_requests exists.
$hasIndex = static function (string $table, string $index) use ($schema): bool {
    return (int) DB::val('SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?', [$schema, $table, $index]) > 0;
};
if ($hasTable('pin_requests') && !$hasIndex('pin_requests', 'uq_pinreq_id_school')) {
    $pdo->exec('ALTER TABLE pin_requests ADD UNIQUE KEY uq_pinreq_id_school (id, school_id)');
    $steps[] = 'Added tenant-safe request key';
}
if (!$hasIndex('scratch_card_batches', 'uq_batch_request')) {
    $pdo->exec('ALTER TABLE scratch_card_batches ADD UNIQUE KEY uq_batch_request (request_id)');
    $steps[] = 'Added one-batch-per-request constraint';
}
if (!$hasIndex('scratch_card_batches', 'ix_batch_class_term')) {
    $pdo->exec('ALTER TABLE scratch_card_batches ADD KEY ix_batch_class_term (school_id, term_id, class_id)');
    $steps[] = 'Added batch class/term index';
}
$hasConstraint = static function (string $table, string $constraint) use ($schema): bool {
    return (int) DB::val('SELECT COUNT(*) FROM information_schema.table_constraints WHERE constraint_schema = ? AND table_name = ? AND constraint_name = ?', [$schema, $table, $constraint]) > 0;
};
if (!$hasConstraint('scratch_card_batches', 'fk_batch_class')) {
    $pdo->exec('ALTER TABLE scratch_card_batches ADD CONSTRAINT fk_batch_class FOREIGN KEY (class_id, school_id) REFERENCES classes (id, school_id)');
    $steps[] = 'Added tenant-safe class relationship to batches';
}
if (!$hasConstraint('scratch_card_batches', 'fk_batch_request')) {
    $pdo->exec('ALTER TABLE scratch_card_batches ADD CONSTRAINT fk_batch_request FOREIGN KEY (request_id, school_id) REFERENCES pin_requests (id, school_id)');
    $steps[] = 'Added request relationship to batches';
}
if ((int) DB::val("SELECT COUNT(*) FROM platform_settings WHERE skey = 'pin_price_per_student'") === 0) {
    DB::run("INSERT INTO platform_settings (skey, svalue) VALUES ('pin_price_per_student', '0.00')");
    $steps[] = 'Added default pin price setting (0.00)';
}
// Run teacher permission normalisation once, even if the deployment flag is left enabled.
if ((int) DB::val("SELECT COUNT(*) FROM platform_settings WHERE skey = 'upgrade_2026_11_17_done'") === 0) {
    DB::run("DELETE FROM user_permissions WHERE permission <> 'scores.enter' AND user_id IN (SELECT id FROM users WHERE role = 'teacher')");
    DB::run("INSERT IGNORE INTO user_permissions (user_id, permission) SELECT id, 'scores.enter' FROM users WHERE role = 'teacher'");
    DB::run("INSERT INTO platform_settings (skey, svalue) VALUES ('upgrade_2026_11_17_done', '1')");
    $steps[] = 'Restricted teacher permissions to score entry';
}

if (!$hasColumn('schools', 'teacher_limit')) {
    $pdo->exec("ALTER TABLE schools ADD COLUMN teacher_limit INT UNSIGNED NULL DEFAULT NULL AFTER admission_prefix");
    $steps[] = 'Added optional per-school teacher limit (NULL = unlimited)';
}
if (!$hasTable('result_verifications')) {
    $pdo->exec("CREATE TABLE result_verifications (
      id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      token CHAR(32) NOT NULL,
      school_id INT UNSIGNED NOT NULL,
      term_id INT UNSIGNED NOT NULL,
      student_id INT UNSIGNED NOT NULL,
      snapshot_hash CHAR(64) NOT NULL,
      issued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      revoked_at DATETIME NULL,
      UNIQUE KEY uq_result_verification_token (token),
      KEY ix_result_verification_student (school_id, term_id, student_id, revoked_at),
      CONSTRAINT fk_rv_school FOREIGN KEY (school_id) REFERENCES schools(id),
      CONSTRAINT fk_rv_term FOREIGN KEY (term_id, school_id) REFERENCES terms(id, school_id),
      CONSTRAINT fk_rv_student FOREIGN KEY (student_id, school_id) REFERENCES students(id, school_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $steps[] = 'Created result authenticity verification table';
}

echo "GRADELY additive upgrade complete. No users, passwords, schools, students or existing results were reset.\n";
foreach ($steps as $step) echo " - $step\n";
if (!$steps) echo " - Database already has all upgrade objects. No changes needed.\n";
