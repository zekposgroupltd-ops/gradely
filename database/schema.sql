-- GRADELY database schema
-- Target: MySQL 5.7+ / MariaDB 10.3+, InnoDB, utf8mb4.
--
-- Multi-tenancy: every school-owned table carries school_id. Parent tables expose
-- UNIQUE (id, school_id) and children reference (parent_id, school_id), so the
-- database itself refuses a row that points at another school's class, student,
-- term, subject, etc. Schools are never hard-deleted (deactivate instead).

SET NAMES utf8mb4;

CREATE TABLE schools (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(190) NOT NULL,
  slug VARCHAR(80) NOT NULL,
  email VARCHAR(190) NULL,
  phone VARCHAR(40) NULL,
  address VARCHAR(255) NULL,
  website VARCHAR(190) NULL,
  logo_path VARCHAR(255) NULL,
  admission_prefix VARCHAR(12) NULL,
  teacher_limit INT UNSIGNED NULL DEFAULT NULL,
  status ENUM('active','suspended','inactive') NOT NULL DEFAULT 'active',
  plan VARCHAR(40) NOT NULL DEFAULT 'standard',
  license_expires_on DATE NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_schools_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  school_id INT UNSIGNED NULL,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('super_admin','school_admin','teacher') NOT NULL,
  status ENUM('active','disabled') NOT NULL DEFAULT 'active',
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_users_email (email),
  UNIQUE KEY uq_users_id_school (id, school_id),
  KEY ix_users_school (school_id),
  CONSTRAINT fk_users_school FOREIGN KEY (school_id) REFERENCES schools (id),
  CONSTRAINT ck_users_scope CHECK ((role = 'super_admin' AND school_id IS NULL) OR (role <> 'super_admin' AND school_id IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fine-grained permissions for teachers (school_admin implicitly has all).
-- e.g. score.enter, results.approve, results.publish, students.manage, cards.manage
CREATE TABLE user_permissions (
  user_id INT UNSIGNED NOT NULL,
  permission VARCHAR(60) NOT NULL,
  PRIMARY KEY (user_id, permission),
  CONSTRAINT fk_uperm_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE school_settings (
  school_id INT UNSIGNED NOT NULL PRIMARY KEY,
  display_mode ENUM('grades','positions','both') NOT NULL DEFAULT 'both',
  tie_mode ENUM('competition','dense') NOT NULL DEFAULT 'competition',
  position_by ENUM('average','total') NOT NULL DEFAULT 'average',
  pass_mark DECIMAL(5,2) NOT NULL DEFAULT 40.00,
  card_binding ENUM('first_student','none') NOT NULL DEFAULT 'first_student',
  allow_download TINYINT(1) NOT NULL DEFAULT 1,
  principal_name VARCHAR(150) NULL,
  report_footer VARCHAR(255) NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_settings_school FOREIGN KEY (school_id) REFERENCES schools (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE academic_sessions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  school_id INT UNSIGNED NOT NULL,
  name VARCHAR(40) NOT NULL,
  is_current TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_sessions_name (school_id, name),
  UNIQUE KEY uq_sessions_id_school (id, school_id),
  CONSTRAINT fk_sessions_school FOREIGN KEY (school_id) REFERENCES schools (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE terms (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  school_id INT UNSIGNED NOT NULL,
  session_id INT UNSIGNED NOT NULL,
  name VARCHAR(40) NOT NULL,
  is_current TINYINT(1) NOT NULL DEFAULT 0,
  start_date DATE NULL,
  end_date DATE NULL,
  next_term_begins DATE NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_terms_name (session_id, name),
  UNIQUE KEY uq_terms_id_school (id, school_id),
  CONSTRAINT fk_terms_session FOREIGN KEY (session_id, school_id) REFERENCES academic_sessions (id, school_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE classes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  school_id INT UNSIGNED NOT NULL,
  name VARCHAR(60) NOT NULL,
  level_order INT NOT NULL DEFAULT 0,
  UNIQUE KEY uq_classes_name (school_id, name),
  UNIQUE KEY uq_classes_id_school (id, school_id),
  CONSTRAINT fk_classes_school FOREIGN KEY (school_id) REFERENCES schools (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE class_arms (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  school_id INT UNSIGNED NOT NULL,
  class_id INT UNSIGNED NOT NULL,
  name VARCHAR(40) NOT NULL,
  UNIQUE KEY uq_arms_name (class_id, name),
  UNIQUE KEY uq_arms_id_school (id, school_id),
  CONSTRAINT fk_arms_class FOREIGN KEY (class_id, school_id) REFERENCES classes (id, school_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE students (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  school_id INT UNSIGNED NOT NULL,
  admission_no VARCHAR(40) NOT NULL,
  first_name VARCHAR(80) NOT NULL,
  last_name VARCHAR(80) NOT NULL,
  other_name VARCHAR(80) NULL,
  gender ENUM('M','F') NULL,
  date_of_birth DATE NULL,
  class_id INT UNSIGNED NOT NULL,
  arm_id INT UNSIGNED NULL,
  status ENUM('active','graduated','withdrawn') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_students_admission (school_id, admission_no),
  UNIQUE KEY uq_students_id_school (id, school_id),
  KEY ix_students_class (school_id, class_id, arm_id),
  CONSTRAINT fk_students_class FOREIGN KEY (class_id, school_id) REFERENCES classes (id, school_id),
  CONSTRAINT fk_students_arm FOREIGN KEY (arm_id, school_id) REFERENCES class_arms (id, school_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE subjects (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  school_id INT UNSIGNED NOT NULL,
  name VARCHAR(100) NOT NULL,
  code VARCHAR(20) NULL,
  UNIQUE KEY uq_subjects_name (school_id, name),
  UNIQUE KEY uq_subjects_id_school (id, school_id),
  CONSTRAINT fk_subjects_school FOREIGN KEY (school_id) REFERENCES schools (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE class_subjects (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  school_id INT UNSIGNED NOT NULL,
  class_id INT UNSIGNED NOT NULL,
  subject_id INT UNSIGNED NOT NULL,
  UNIQUE KEY uq_class_subject (class_id, subject_id),
  CONSTRAINT fk_cs_class FOREIGN KEY (class_id, school_id) REFERENCES classes (id, school_id),
  CONSTRAINT fk_cs_subject FOREIGN KEY (subject_id, school_id) REFERENCES subjects (id, school_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Which teacher may enter scores for which class/subject.
CREATE TABLE teacher_assignments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  school_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  class_id INT UNSIGNED NOT NULL,
  subject_id INT UNSIGNED NOT NULL,
  UNIQUE KEY uq_teacher_assign (user_id, class_id, subject_id),
  CONSTRAINT fk_ta_user FOREIGN KEY (user_id, school_id) REFERENCES users (id, school_id),
  CONSTRAINT fk_ta_class FOREIGN KEY (class_id, school_id) REFERENCES classes (id, school_id),
  CONSTRAINT fk_ta_subject FOREIGN KEY (subject_id, school_id) REFERENCES subjects (id, school_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Configurable assessment components (e.g. Test 1 = 10, Assignment = 10, Exam = 60).
CREATE TABLE assessment_components (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  school_id INT UNSIGNED NOT NULL,
  name VARCHAR(60) NOT NULL,
  max_score DECIMAL(5,2) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_exam TINYINT(1) NOT NULL DEFAULT 0,
  UNIQUE KEY uq_components_name (school_id, name),
  UNIQUE KEY uq_components_id_school (id, school_id),
  CONSTRAINT fk_components_school FOREIGN KEY (school_id) REFERENCES schools (id),
  CONSTRAINT ck_components_max CHECK (max_score > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Per-school grading structure.
CREATE TABLE grade_boundaries (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  school_id INT UNSIGNED NOT NULL,
  grade VARCHAR(5) NOT NULL,
  min_score DECIMAL(5,2) NOT NULL,
  max_score DECIMAL(5,2) NOT NULL,
  remark VARCHAR(60) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  UNIQUE KEY uq_grade (school_id, grade),
  CONSTRAINT fk_grades_school FOREIGN KEY (school_id) REFERENCES schools (id),
  CONSTRAINT ck_grades_range CHECK (min_score <= max_score)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Original score records. Every change is mirrored into score_history.
CREATE TABLE scores (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  school_id INT UNSIGNED NOT NULL,
  term_id INT UNSIGNED NOT NULL,
  student_id INT UNSIGNED NOT NULL,
  subject_id INT UNSIGNED NOT NULL,
  component_id INT UNSIGNED NOT NULL,
  score DECIMAL(6,2) NOT NULL,
  entered_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_score_cell (term_id, student_id, subject_id, component_id),
  KEY ix_scores_term_subject (school_id, term_id, subject_id),
  CONSTRAINT fk_scores_term FOREIGN KEY (term_id, school_id) REFERENCES terms (id, school_id),
  CONSTRAINT fk_scores_student FOREIGN KEY (student_id, school_id) REFERENCES students (id, school_id),
  CONSTRAINT fk_scores_subject FOREIGN KEY (subject_id, school_id) REFERENCES subjects (id, school_id),
  CONSTRAINT fk_scores_component FOREIGN KEY (component_id, school_id) REFERENCES assessment_components (id, school_id),
  CONSTRAINT ck_scores_nonneg CHECK (score >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Append-only correction trail. Deliberately no foreign keys so history survives deletes.
CREATE TABLE score_history (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  school_id INT UNSIGNED NOT NULL,
  term_id INT UNSIGNED NOT NULL,
  student_id INT UNSIGNED NOT NULL,
  subject_id INT UNSIGNED NOT NULL,
  component_id INT UNSIGNED NOT NULL,
  old_score DECIMAL(6,2) NULL,
  new_score DECIMAL(6,2) NULL,
  action ENUM('create','update','delete','import') NOT NULL,
  reason VARCHAR(255) NULL,
  changed_by INT UNSIGNED NULL,
  changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_history_cell (school_id, term_id, student_id, subject_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Computed by the backend result engine (recomputable from scores).
CREATE TABLE subject_results (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  school_id INT UNSIGNED NOT NULL,
  term_id INT UNSIGNED NOT NULL,
  student_id INT UNSIGNED NOT NULL,
  subject_id INT UNSIGNED NOT NULL,
  class_id INT UNSIGNED NOT NULL,
  total DECIMAL(6,2) NOT NULL,
  grade VARCHAR(5) NULL,
  remark VARCHAR(60) NULL,
  subject_position INT NULL,
  UNIQUE KEY uq_subject_result (term_id, student_id, subject_id),
  CONSTRAINT fk_sr_term FOREIGN KEY (term_id, school_id) REFERENCES terms (id, school_id),
  CONSTRAINT fk_sr_student FOREIGN KEY (student_id, school_id) REFERENCES students (id, school_id),
  CONSTRAINT fk_sr_subject FOREIGN KEY (subject_id, school_id) REFERENCES subjects (id, school_id),
  CONSTRAINT fk_sr_class FOREIGN KEY (class_id, school_id) REFERENCES classes (id, school_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE result_summaries (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  school_id INT UNSIGNED NOT NULL,
  term_id INT UNSIGNED NOT NULL,
  student_id INT UNSIGNED NOT NULL,
  class_id INT UNSIGNED NOT NULL,
  arm_id INT UNSIGNED NULL,
  subjects_count INT NOT NULL DEFAULT 0,
  total DECIMAL(8,2) NOT NULL DEFAULT 0,
  average DECIMAL(6,2) NOT NULL DEFAULT 0,
  position INT NULL,
  class_size INT NULL,
  status ENUM('draft','approved','published') NOT NULL DEFAULT 'draft',
  teacher_comment VARCHAR(500) NULL,
  principal_comment VARCHAR(500) NULL,
  approved_by INT UNSIGNED NULL,
  approved_at DATETIME NULL,
  published_at DATETIME NULL,
  UNIQUE KEY uq_summary (term_id, student_id),
  KEY ix_summary_class (school_id, term_id, class_id, status),
  CONSTRAINT fk_rs_term FOREIGN KEY (term_id, school_id) REFERENCES terms (id, school_id),
  CONSTRAINT fk_rs_student FOREIGN KEY (student_id, school_id) REFERENCES students (id, school_id),
  CONSTRAINT fk_rs_class FOREIGN KEY (class_id, school_id) REFERENCES classes (id, school_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE result_publications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  school_id INT UNSIGNED NOT NULL,
  term_id INT UNSIGNED NOT NULL,
  class_id INT UNSIGNED NULL,
  action ENUM('approve','publish','unpublish','revert') NOT NULL,
  acted_by INT UNSIGNED NULL,
  acted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_pub_term (school_id, term_id),
  CONSTRAINT fk_pub_term FOREIGN KEY (term_id, school_id) REFERENCES terms (id, school_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Public authenticity tokens for exact published report snapshots.
CREATE TABLE result_verifications (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pin_requests (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE scratch_card_batches (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  school_id INT UNSIGNED NOT NULL,
  session_id INT UNSIGNED NOT NULL,
  term_id INT UNSIGNED NOT NULL,
  class_id INT UNSIGNED NULL,
  request_id BIGINT UNSIGNED NULL,
  quantity INT UNSIGNED NOT NULL,
  label VARCHAR(100) NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_batches_id_school (id, school_id),
  CONSTRAINT fk_batch_session FOREIGN KEY (session_id, school_id) REFERENCES academic_sessions (id, school_id),
  UNIQUE KEY uq_batch_request (request_id),
  KEY ix_batch_class_term (school_id, term_id, class_id),
  CONSTRAINT fk_batch_term FOREIGN KEY (term_id, school_id) REFERENCES terms (id, school_id),
  CONSTRAINT fk_batch_class FOREIGN KEY (class_id, school_id) REFERENCES classes (id, school_id),
  CONSTRAINT fk_batch_request FOREIGN KEY (request_id, school_id) REFERENCES pin_requests (id, school_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- PINs are never stored in plaintext: pin_hash = HMAC-SHA256(pin, APP_SECRET).
-- The plaintext PIN is shown/exported once, at generation time.
CREATE TABLE scratch_cards (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  school_id INT UNSIGNED NOT NULL,
  batch_id INT UNSIGNED NOT NULL,
  session_id INT UNSIGNED NOT NULL,
  term_id INT UNSIGNED NOT NULL,
  serial VARCHAR(20) NOT NULL,
  pin_hash CHAR(64) NOT NULL,
  status ENUM('active','exhausted','revoked','expired') NOT NULL DEFAULT 'active',
  use_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
  max_uses TINYINT UNSIGNED NOT NULL DEFAULT 5,
  bound_student_id INT UNSIGNED NULL,
  first_used_at DATETIME NULL,
  last_used_at DATETIME NULL,
  expires_at DATETIME NULL,
  revoked_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_cards_serial (serial),
  UNIQUE KEY uq_cards_pin (pin_hash),
  KEY ix_cards_inventory (school_id, term_id, status),
  CONSTRAINT fk_cards_batch FOREIGN KEY (batch_id, school_id) REFERENCES scratch_card_batches (id, school_id),
  CONSTRAINT fk_cards_session FOREIGN KEY (session_id, school_id) REFERENCES academic_sessions (id, school_id),
  CONSTRAINT fk_cards_term FOREIGN KEY (term_id, school_id) REFERENCES terms (id, school_id),
  CONSTRAINT fk_cards_student FOREIGN KEY (bound_student_id, school_id) REFERENCES students (id, school_id),
  CONSTRAINT ck_cards_uses CHECK (use_count <= max_uses)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per SUCCESSFUL check. Failed attempts are never recorded here.
CREATE TABLE scratch_card_usages (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  card_id BIGINT UNSIGNED NOT NULL,
  school_id INT UNSIGNED NOT NULL,
  term_id INT UNSIGNED NOT NULL,
  student_id INT UNSIGNED NOT NULL,
  ip VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  used_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_usage_card (card_id),
  CONSTRAINT fk_usage_card FOREIGN KEY (card_id) REFERENCES scratch_cards (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  school_id INT UNSIGNED NULL,
  user_id INT UNSIGNED NULL,
  action VARCHAR(80) NOT NULL,
  entity VARCHAR(60) NULL,
  entity_id VARCHAR(40) NULL,
  meta TEXT NULL,
  ip VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_audit_school (school_id, created_at),
  KEY ix_audit_action (action, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notifications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  school_id INT UNSIGNED NULL,
  user_id INT UNSIGNED NULL,
  title VARCHAR(150) NOT NULL,
  body VARCHAR(500) NULL,
  read_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_notif_user (user_id, read_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE platform_settings (
  skey VARCHAR(60) NOT NULL PRIMARY KEY,
  svalue VARCHAR(500) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO platform_settings (skey, svalue) VALUES ('pin_price_per_student', '0.00');

CREATE TABLE rate_limits (
  rkey CHAR(64) NOT NULL PRIMARY KEY,
  hits INT UNSIGNED NOT NULL DEFAULT 0,
  window_start INT UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
