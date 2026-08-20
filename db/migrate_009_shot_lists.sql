-- Migration: adds shot lists — a per-booking document with named sections
-- (e.g. Establishing, Survey, Detection) each holding a bullet list of shot
-- ideas, printable from the app.
-- Run this ONCE via phpMyAdmin against the live IONOS database, BEFORE
-- pushing/deploying the corresponding code changes.

CREATE TABLE IF NOT EXISTS shot_lists (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_id  INT UNSIGNED NOT NULL UNIQUE,
  subtitle    VARCHAR(255) NULL,
  sections    JSON NULL,
  created_by  VARCHAR(255) NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
) ENGINE=InnoDB;
