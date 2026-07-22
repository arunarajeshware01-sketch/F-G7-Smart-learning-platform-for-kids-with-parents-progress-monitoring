-- ============================================================
-- One-time migration.
-- Run this ONCE in phpMyAdmin (SQL tab, on the smart_learning database)
-- to add the new "classes" table that lets admin add/rename class
-- levels instead of them being hardcoded as Class 1-5 everywhere.
-- Safe to run even if the table already exists.
-- ============================================================

USE smart_learning;

CREATE TABLE IF NOT EXISTS classes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL UNIQUE,
  sort_order INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT IGNORE INTO classes (name, sort_order) VALUES
  ('Class 1', 1), ('Class 2', 2), ('Class 3', 3), ('Class 4', 4), ('Class 5', 5);
