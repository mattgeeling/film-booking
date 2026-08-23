-- Migration: records the resolved place name used for the call sheet's
-- live weather lookup (since geocoding can resolve to a nearby town or
-- postcode rather than the exact typed address), so it can be shown next
-- to the forecast for reference.
-- Run this ONCE via phpMyAdmin against the live IONOS database, BEFORE
-- pushing/deploying the corresponding code changes.

ALTER TABLE call_sheets
  ADD COLUMN weather_location VARCHAR(500) NULL AFTER weather_icons;
