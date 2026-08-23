-- Migration: adds sunrise/sunset to call sheets (from the same Open-Meteo
-- lookup used for weather), and a new ra_templates table so risk assessment
-- hazard/standard-arrangement sets can be saved and reused per shoot type
-- (e.g. "Drone shoot", "Studio shoot") beyond the single built-in default.
-- Run this ONCE via phpMyAdmin against the live IONOS database, BEFORE
-- pushing/deploying the corresponding code changes.

ALTER TABLE call_sheets
  ADD COLUMN sunrise_sunset VARCHAR(100) NULL AFTER weather_location,
  ADD COLUMN weather_location_override VARCHAR(255) NULL AFTER sunrise_sunset;

CREATE TABLE IF NOT EXISTS ra_templates (
  id                     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name                   VARCHAR(255) NOT NULL,
  standard_arrangements  JSON NULL,
  hazards                JSON NULL,
  created_by             VARCHAR(255) NULL,
  created_at             TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at             TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;
