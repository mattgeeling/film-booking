-- Migration: adds per-person unavailable days (e.g. "Nigel isn't free next
-- Wed"), distinct from blocked_days which blocks a day for everyone.
-- Run this ONCE via phpMyAdmin against the live IONOS database, BEFORE
-- pushing/deploying the corresponding code changes.

CREATE TABLE IF NOT EXISTS person_unavailable_days (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  person_id  INT UNSIGNED NOT NULL,
  day        DATE NOT NULL,
  reason     VARCHAR(255) NULL,
  created_by VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_person_day (person_id, day),
  FOREIGN KEY (person_id) REFERENCES people(id) ON DELETE CASCADE
) ENGINE=InnoDB;
