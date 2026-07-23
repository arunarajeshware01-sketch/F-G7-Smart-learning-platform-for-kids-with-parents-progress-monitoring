-- ============================================================
-- One-time migration.
-- Run this ONCE in phpMyAdmin (SQL tab, on the smart_learning database)
-- to add the new table that powers the real "Learning activity" chart
-- on the parent dashboard (previously that chart was fake/hardcoded).
--
-- Safe to run even if the table already exists elsewhere — it just
-- creates it if missing.
-- ============================================================

USE smart_learning;

CREATE TABLE IF NOT EXISTS daily_activity (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  activity_date DATE NOT NULL,
  minutes INT DEFAULT 0,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  UNIQUE KEY unique_student_date (student_id, activity_date)
);
