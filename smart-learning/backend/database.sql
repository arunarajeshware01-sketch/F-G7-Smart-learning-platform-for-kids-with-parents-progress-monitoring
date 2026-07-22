-- ============================================================
-- Smart Learning Platform for Kids — Database Schema
-- Import this file in phpMyAdmin (XAMPP) to create everything.
-- ============================================================

CREATE DATABASE IF NOT EXISTS smart_learning CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE smart_learning;

-- ---------- ADMIN ----------
CREATE TABLE admins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ---------- PARENTS ----------
CREATE TABLE parents (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  mobile VARCHAR(20) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ---------- STUDENTS ----------
-- Note: the original SRS (FR1) does not collect a student email for login.
-- Since FR3 requires every role to log in with email + password, a
-- student login email has been added here. Explained in SETUP_GUIDE.md.
CREATE TABLE students (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  age INT NOT NULL,
  class VARCHAR(20) NOT NULL,
  parent_mobile VARCHAR(20) NOT NULL,
  parent_email VARCHAR(150) NOT NULL,
  parent_id INT NULL,
  daily_goal_minutes INT DEFAULT 30,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (parent_id) REFERENCES parents(id) ON DELETE SET NULL
);

-- ---------- SUBJECTS (fixed 4 subjects per SRS scope) ----------
CREATE TABLE subjects (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE
);

INSERT INTO subjects (name) VALUES
  ('English'), ('Mathematics'), ('Environmental Studies (EVS)'), ('Basic Computer Knowledge');

-- ---------- CLASSES (master list, so admin can add/rename class levels) ----------
CREATE TABLE classes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL UNIQUE,
  sort_order INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO classes (name, sort_order) VALUES
  ('Class 1', 1), ('Class 2', 2), ('Class 3', 3), ('Class 4', 4), ('Class 5', 5);

-- ---------- LESSONS (animated lessons / study material) ----------
CREATE TABLE lessons (
  id INT AUTO_INCREMENT PRIMARY KEY,
  subject_id INT NOT NULL,
  class_level VARCHAR(20) NOT NULL,
  title VARCHAR(200) NOT NULL,
  description TEXT,
  content_url VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
);

-- ---------- VIDEOS ----------
CREATE TABLE videos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  subject_id INT NOT NULL,
  class_level VARCHAR(20) NOT NULL,
  title VARCHAR(200) NOT NULL,
  video_url VARCHAR(255) NOT NULL,
  uploaded_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
  FOREIGN KEY (uploaded_by) REFERENCES admins(id) ON DELETE SET NULL
);

-- ---------- QUIZZES ----------
CREATE TABLE quizzes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  subject_id INT NOT NULL,
  class_level VARCHAR(20) NOT NULL,
  title VARCHAR(200) NOT NULL,
  created_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES admins(id) ON DELETE SET NULL
);

CREATE TABLE quiz_questions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  quiz_id INT NOT NULL,
  question_text TEXT NOT NULL,
  option_a VARCHAR(255) NOT NULL,
  option_b VARCHAR(255) NOT NULL,
  option_c VARCHAR(255) NOT NULL,
  option_d VARCHAR(255) NOT NULL,
  correct_option ENUM('A','B','C','D') NOT NULL,
  FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
);

CREATE TABLE quiz_results (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  quiz_id INT NOT NULL,
  score INT NOT NULL,
  total_questions INT NOT NULL,
  attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
);

-- ---------- PROGRESS (per subject, used for dashboards + reports) ----------
CREATE TABLE progress (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  subject_id INT NOT NULL,
  lessons_completed INT DEFAULT 0,
  learning_time_minutes INT DEFAULT 0,
  last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
  UNIQUE KEY unique_student_subject (student_id, subject_id)
);

-- ---------- CONTENT COMPLETIONS ----------
-- Tracks which video/lesson a student has already been credited for,
-- so rewatching the same video doesn't inflate "lessons completed" again.
CREATE TABLE content_completions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  content_type ENUM('lesson','video') NOT NULL,
  content_id INT NOT NULL,
  completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  UNIQUE KEY unique_completion (student_id, content_type, content_id)
);

-- ---------- DAILY ACTIVITY (per-day learning minutes, for the parent chart) ----------
CREATE TABLE daily_activity (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  activity_date DATE NOT NULL,
  minutes INT DEFAULT 0,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  UNIQUE KEY unique_student_date (student_id, activity_date)
);

-- ---------- NOTIFICATIONS ----------
CREATE TABLE notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  recipient_type ENUM('student','parent') NOT NULL,
  recipient_id INT NOT NULL,
  message VARCHAR(500) NOT NULL,
  is_read TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ---------- DEFAULT ADMIN ----------
-- email: admin@smartlearning.com   password: Admin@123
-- (hash generated with PHP password_hash, bcrypt)
INSERT INTO admins (name, email, password) VALUES
('Super Admin', 'admin@smartlearning.com', '$2b$10$y8gHEZSZOFSaHetueI/aa.1M27xRmBh5FkUKS4eAXka3WTjMDsyoi');
