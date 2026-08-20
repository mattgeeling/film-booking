-- Migration: adds call sheets — a per-booking document with location
-- contact/parking details, production/client crew lists with call times,
-- equipment, a schedule, and nearest-hospital info, printable from the app.
-- Run this ONCE via phpMyAdmin against the live IONOS database, BEFORE
-- pushing/deploying the corresponding code changes.

CREATE TABLE IF NOT EXISTS call_sheets (
  id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_id              INT UNSIGNED NOT NULL UNIQUE,
  day_info                VARCHAR(100) NULL,
  location_contact_name   VARCHAR(255) NULL,
  location_contact_phone  VARCHAR(50) NULL,
  parking_notes           TEXT NULL,
  weather_summary         TEXT NULL,
  weather_icons           JSON NULL,
  production_crew         JSON NULL,
  client_contacts         JSON NULL,
  equipment               JSON NULL,
  schedule                JSON NULL,
  nearest_ae              TEXT NULL,
  created_by              VARCHAR(255) NULL,
  created_at              TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at              TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
) ENGINE=InnoDB;
