-- Migration: adds AM/PM granularity to person_unavailable_days (previously
-- always a whole-day entry). Run this ONCE via phpMyAdmin against the live
-- IONOS database, BEFORE pushing/deploying the corresponding code changes.

ALTER TABLE person_unavailable_days
  ADD COLUMN period ENUM('all_day', 'am', 'pm') NOT NULL DEFAULT 'all_day' AFTER day,
  DROP INDEX uniq_person_day,
  ADD UNIQUE KEY uniq_person_day_period (person_id, day, period);
