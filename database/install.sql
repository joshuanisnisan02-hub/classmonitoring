CREATE DATABASE IF NOT EXISTS cbit_class_monitoring
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE cbit_class_monitoring;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(80) NOT NULL UNIQUE,
  display_name VARCHAR(160) NOT NULL DEFAULT '',
  password_hash VARCHAR(255) NOT NULL,
  role VARCHAR(50) NOT NULL DEFAULT 'Coordinator',
  college_account VARCHAR(20) NOT NULL DEFAULT 'CBIT',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_users_college (college_account),
  INDEX idx_users_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



INSERT INTO users (username, display_name, password_hash, role, college_account, is_active) VALUES
('admin', 'System Administrator', '$2y$12$ad9SUV8QusTLhEQUDJ8gp.a.WPv/ugwKBJ3Da4YXj0Uzp/q6kbzHC', 'Admin', 'ALL', 1),
('cbit', 'CBIT Account', '$2y$12$kanD9xIamcwBvb62hwi6WuHr/vR2e8nQuJ2RCzrJtvgMRGoI4ZiqS', 'CBIT', 'CBIT', 1),
('cssh', 'CSSH Account', '$2y$12$L3dddiDXW6OAOg9ZpxOKoOEvPM33rgwMqymXUWhRxYRcZcsCDKWiK', 'CSSH', 'CSSH', 1)
ON DUPLICATE KEY UPDATE
  display_name = VALUES(display_name),
  password_hash = VALUES(password_hash),
  role = VALUES(role),
  college_account = VALUES(college_account),
  is_active = VALUES(is_active);

CREATE TABLE IF NOT EXISTS settings (
  setting_key VARCHAR(100) PRIMARY KEY,
  setting_value TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS class_records (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  college_account VARCHAR(20) NOT NULL DEFAULT 'CBIT',
  instructor VARCHAR(255) NOT NULL DEFAULT '',
  course VARCHAR(255) NOT NULL DEFAULT '',
  section VARCHAR(150) NOT NULL DEFAULT '',
  subject VARCHAR(255) NOT NULL DEFAULT '',
  day VARCHAR(100) NOT NULL DEFAULT '',
  schedule_text VARCHAR(255) NOT NULL DEFAULT '',
  cronasia_pmvgo_checks VARCHAR(100) NOT NULL DEFAULT '',
  class_material_1_2 VARCHAR(255) NOT NULL DEFAULT '',
  class_material_3_4 VARCHAR(255) NOT NULL DEFAULT '',
  activity_1_2 VARCHAR(255) NOT NULL DEFAULT '',
  activity_3_4 VARCHAR(255) NOT NULL DEFAULT '',
  total_classes INT NOT NULL DEFAULT 0,
  present INT NOT NULL DEFAULT 0,
  absent INT NOT NULL DEFAULT 0,
  remarks TEXT NULL,
  monitoring_status VARCHAR(60) NOT NULL DEFAULT 'Pending',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_instructor (instructor),
  INDEX idx_college (college_account),
  INDEX idx_course (course),
  INDEX idx_status (monitoring_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO settings (setting_key, setting_value) VALUES
('college_name', 'COLLEGE OF BUSINESS AND INFORMATION TECHNOLOGY'),
('academic_year', '2026-2027'),
('semester', 'FIRST SEMESTER'),
('course_title', 'CBIT CLASS MONITORING'),
('report_title', 'TEACHER''S MONITORING SUMMARY'),
('covered_dates', 'JULY 6-12, 2026')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
