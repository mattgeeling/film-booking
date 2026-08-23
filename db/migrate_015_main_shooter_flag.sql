-- Migration: adds a "main shooter" flag to people, used to prefill the
-- Call Sheet/Risk Assessment crew list for standalone documents (which have
-- no real booking attendees to prefill from) with just the core shooters
-- rather than everyone in the People list.
-- Run this ONCE via phpMyAdmin against the live IONOS database, BEFORE
-- pushing/deploying the corresponding code changes.

ALTER TABLE people
  ADD COLUMN is_main_shooter TINYINT(1) NOT NULL DEFAULT 0 AFTER role;

-- Best-effort seed based on names mentioned so far — adjust via the People
-- page's "Main shooter" checkbox afterwards if this doesn't match exactly.
UPDATE people SET is_main_shooter = 1 WHERE name IN ('Nigel Moore', 'Tom Ellison', 'Mark Kuczewski');
