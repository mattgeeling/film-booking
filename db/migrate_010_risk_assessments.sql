-- Migration: adds risk assessments — a per-booking document with client/
-- director/PM details, brief description, crew list, standard fire/first
-- aid/welfare arrangements, a dynamic hazards & precautions table, nearest
-- A&E, and a sign-off block, printable from the app.
-- Run this ONCE via phpMyAdmin against the live IONOS database, BEFORE
-- pushing/deploying the corresponding code changes.

CREATE TABLE IF NOT EXISTS risk_assessments (
  id                        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_id                INT UNSIGNED NOT NULL UNIQUE,
  client_name               VARCHAR(255) NULL,
  location_contact          VARCHAR(255) NULL,
  director_name             VARCHAR(255) NULL,
  director_email            VARCHAR(255) NULL,
  director_mobile           VARCHAR(50) NULL,
  production_manager_name   VARCHAR(255) NULL,
  production_manager_email  VARCHAR(255) NULL,
  production_manager_mobile VARCHAR(50) NULL,
  brief_description         TEXT NULL,
  crew_experts              TEXT NULL,
  nearest_ae                TEXT NULL,
  standard_arrangements     JSON NULL,
  hazards                   JSON NULL,
  signoff_director_name     VARCHAR(255) NULL,
  signoff_director_date     DATE NULL,
  signoff_producer_name     VARCHAR(255) NULL,
  signoff_producer_date     DATE NULL,
  created_by                VARCHAR(255) NULL,
  created_at                TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at                TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
) ENGINE=InnoDB;
