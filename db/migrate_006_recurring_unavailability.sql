-- Migration: adds recurring weekly unavailability (e.g. "Mark isn't
-- available every Monday and Friday"), for standing/part-time patterns —
-- distinct from person_unavailable_days, which is for one-off dates.
-- weekday follows ISO-8601: 1=Monday .. 7=Sunday.
-- Run this ONCE via phpMyAdmin against the live IONOS database, BEFORE
-- pushing/deploying the corresponding code changes.

CREATE TABLE IF NOT EXISTS person_recurring_unavailability (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  person_id  INT UNSIGNED NOT NULL,
  weekday    TINYINT UNSIGNED NOT NULL,
  period     ENUM('all_day','am','pm') NOT NULL DEFAULT 'all_day',
  reason     VARCHAR(255) NULL,
  created_by VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_person_weekday_period (person_id, weekday, period),
  FOREIGN KEY (person_id) REFERENCES people(id) ON DELETE CASCADE
) ENGINE=InnoDB;
