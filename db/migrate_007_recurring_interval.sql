-- Migration: adds interval support to recurring unavailability, so a rule
-- can be "every week" (default) or "every N weeks from a given date"
-- (e.g. Tom is unavailable every other Friday).
-- Run this ONCE via phpMyAdmin against the live IONOS database, BEFORE
-- pushing/deploying the corresponding code changes.

ALTER TABLE person_recurring_unavailability
  ADD COLUMN interval_weeks TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER weekday,
  ADD COLUMN anchor_date DATE NULL AFTER interval_weeks;
