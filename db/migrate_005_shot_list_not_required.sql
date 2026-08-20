-- Migration: adds a "not required" flag for the Shot List checklist item
-- (since not every shoot needs one), and a new optional "Additional
-- documents" checklist item. Neither is counted as missing in the Needs
-- Prep report when not applicable/not ticked.
-- Run this ONCE via phpMyAdmin against the live IONOS database, BEFORE
-- pushing/deploying the corresponding code changes.

ALTER TABLE bookings
  ADD COLUMN checklist_shot_list_na TINYINT(1) NOT NULL DEFAULT 0 AFTER checklist_shot_list_url,
  ADD COLUMN checklist_additional_documents TINYINT(1) NOT NULL DEFAULT 0 AFTER checklist_preproduction_creative_url,
  ADD COLUMN checklist_additional_documents_by VARCHAR(255) NULL AFTER checklist_additional_documents,
  ADD COLUMN checklist_additional_documents_url VARCHAR(500) NULL AFTER checklist_additional_documents_by;
