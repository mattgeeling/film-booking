-- Migration: adds a static map screenshot (as a data: URI) of the call
-- sheet's geocoded location, shown in the editor and printed/emailed with
-- the document.
-- Run this ONCE via phpMyAdmin against the live IONOS database, BEFORE
-- pushing/deploying the corresponding code changes.

ALTER TABLE call_sheets
  ADD COLUMN location_map MEDIUMTEXT NULL AFTER nearest_ae;
