-- Migration: allows Call Sheets and Risk Assessments to be created as
-- standalone documents (via the dashboard's "Generator" tools), not tied
-- to a real shoot booking. These are stored as ordinary bookings flagged
-- with is_standalone_doc so they're excluded from the calendar/needs-prep
-- views, keeping the existing call_sheet/risk_assessment editors untouched.
-- Run this ONCE via phpMyAdmin against the live IONOS database, BEFORE
-- pushing/deploying the corresponding code changes.

ALTER TABLE bookings
  ADD COLUMN is_standalone_doc TINYINT(1) NOT NULL DEFAULT 0 AFTER status,
  ADD COLUMN doc_type VARCHAR(20) NULL AFTER is_standalone_doc;
