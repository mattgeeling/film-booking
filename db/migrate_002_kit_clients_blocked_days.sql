-- Migration: brings the production database (as deployed from the
-- "Replace google/apiclient SDK" commit) up to the current schema.
-- Run this ONCE via phpMyAdmin against the live IONOS database, BEFORE
-- pushing/deploying the corresponding code changes — otherwise the live
-- site will error on every booking operation.

CREATE TABLE IF NOT EXISTS clients (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(150)  NOT NULL,
  logo_path  VARCHAR(255)  NULL,
  active     TINYINT(1)    NOT NULL DEFAULT 1,
  created_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS blocked_days (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  day        DATE NOT NULL UNIQUE,
  reason     VARCHAR(255) NULL,
  created_by VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

ALTER TABLE bookings
  ADD COLUMN what3words VARCHAR(255) NULL AFTER location,
  ADD COLUMN client_id INT UNSIGNED NULL AFTER what3words,
  ADD COLUMN checklist_call_sheet TINYINT(1) NOT NULL DEFAULT 0 AFTER status,
  ADD COLUMN checklist_call_sheet_by VARCHAR(255) NULL AFTER checklist_call_sheet,
  ADD COLUMN checklist_call_sheet_url VARCHAR(500) NULL AFTER checklist_call_sheet_by,
  ADD COLUMN checklist_risk_assessment TINYINT(1) NOT NULL DEFAULT 0 AFTER checklist_call_sheet_url,
  ADD COLUMN checklist_risk_assessment_by VARCHAR(255) NULL AFTER checklist_risk_assessment,
  ADD COLUMN checklist_risk_assessment_url VARCHAR(500) NULL AFTER checklist_risk_assessment_by,
  ADD COLUMN checklist_shot_list TINYINT(1) NOT NULL DEFAULT 0 AFTER checklist_risk_assessment_url,
  ADD COLUMN checklist_shot_list_by VARCHAR(255) NULL AFTER checklist_shot_list,
  ADD COLUMN checklist_shot_list_url VARCHAR(500) NULL AFTER checklist_shot_list_by,
  ADD COLUMN checklist_preproduction_creative TINYINT(1) NOT NULL DEFAULT 0 AFTER checklist_shot_list_url,
  ADD COLUMN checklist_preproduction_creative_by VARCHAR(255) NULL AFTER checklist_preproduction_creative,
  ADD COLUMN checklist_preproduction_creative_url VARCHAR(500) NULL AFTER checklist_preproduction_creative_by,
  ADD COLUMN skip_calendar_sync TINYINT(1) NOT NULL DEFAULT 0 AFTER checklist_preproduction_creative_url,
  ADD COLUMN kit_source ENUM('fuzzy_duck','mark','tom') NOT NULL DEFAULT 'fuzzy_duck' AFTER skip_calendar_sync,
  ADD COLUMN created_by_name VARCHAR(255) NULL AFTER created_by,
  ADD FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL;
