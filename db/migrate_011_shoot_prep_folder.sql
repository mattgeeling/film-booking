-- Migration: adds a per-booking Google Drive "Shoot Prep" folder link,
-- included in the booking confirmation email.
-- Run this ONCE via phpMyAdmin against the live IONOS database, BEFORE
-- pushing/deploying the corresponding code changes.

ALTER TABLE bookings
  ADD COLUMN shoot_prep_folder_url VARCHAR(500) NULL AFTER what3words;
