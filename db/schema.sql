CREATE TABLE IF NOT EXISTS people (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(100)  NOT NULL,
  role       VARCHAR(100)  NULL,
  email      VARCHAR(255)  NOT NULL UNIQUE,
  active     TINYINT(1)    NOT NULL DEFAULT 1,
  created_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS clients (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(150)  NOT NULL,
  logo_path  VARCHAR(255)  NULL,
  active     TINYINT(1)    NOT NULL DEFAULT 1,
  created_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS bookings (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title          VARCHAR(255) NOT NULL,
  location       VARCHAR(255) NULL,
  what3words     VARCHAR(255) NULL,
  client_id      INT UNSIGNED NULL,
  notes          TEXT NULL,
  start_datetime DATETIME NOT NULL,
  end_datetime   DATETIME NOT NULL,
  status         ENUM('pencil','confirmed','cancelled') NOT NULL DEFAULT 'pencil',
  checklist_call_sheet             TINYINT(1)   NOT NULL DEFAULT 0,
  checklist_call_sheet_by          VARCHAR(255) NULL,
  checklist_call_sheet_url         VARCHAR(500) NULL,
  checklist_risk_assessment        TINYINT(1)   NOT NULL DEFAULT 0,
  checklist_risk_assessment_by     VARCHAR(255) NULL,
  checklist_risk_assessment_url    VARCHAR(500) NULL,
  checklist_shot_list               TINYINT(1)   NOT NULL DEFAULT 0,
  checklist_shot_list_by            VARCHAR(255) NULL,
  checklist_shot_list_url           VARCHAR(500) NULL,
  checklist_shot_list_na            TINYINT(1)   NOT NULL DEFAULT 0,
  checklist_preproduction_creative     TINYINT(1)   NOT NULL DEFAULT 0,
  checklist_preproduction_creative_by  VARCHAR(255) NULL,
  checklist_preproduction_creative_url VARCHAR(500) NULL,
  checklist_additional_documents       TINYINT(1)   NOT NULL DEFAULT 0,
  checklist_additional_documents_by    VARCHAR(255) NULL,
  checklist_additional_documents_url   VARCHAR(500) NULL,
  skip_calendar_sync TINYINT(1) NOT NULL DEFAULT 0,
  kit_source ENUM('fuzzy_duck','mark','tom') NOT NULL DEFAULT 'fuzzy_duck',
  created_by     VARCHAR(255) NULL,
  created_by_name VARCHAR(255) NULL,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  confirmed_at   TIMESTAMP NULL,
  cancelled_at   TIMESTAMP NULL,
  INDEX idx_start (start_datetime),
  INDEX idx_status (status),
  FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS booking_people (
  booking_id INT UNSIGNED NOT NULL,
  person_id  INT UNSIGNED NOT NULL,
  PRIMARY KEY (booking_id, person_id),
  FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
  FOREIGN KEY (person_id)  REFERENCES people(id)   ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Tracks the real Google Calendar event created per (booking, attendee) pair,
-- so edits/cancellations know exactly which event to patch/delete on whose calendar.
CREATE TABLE IF NOT EXISTS booking_calendar_events (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_id         INT UNSIGNED NOT NULL,
  person_id          INT UNSIGNED NOT NULL,
  google_calendar_id VARCHAR(255) NOT NULL DEFAULT 'primary',
  google_event_id    VARCHAR(255) NOT NULL,
  synced_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_sync_status   ENUM('ok','error') NOT NULL DEFAULT 'ok',
  last_sync_error    TEXT NULL,
  UNIQUE KEY uniq_booking_person (booking_id, person_id),
  FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
  FOREIGN KEY (person_id)  REFERENCES people(id)   ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS blocked_days (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  day        DATE NOT NULL UNIQUE,
  reason     VARCHAR(255) NULL,
  created_by VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS person_unavailable_days (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  person_id  INT UNSIGNED NOT NULL,
  day        DATE NOT NULL,
  period     ENUM('all_day','am','pm') NOT NULL DEFAULT 'all_day',
  reason     VARCHAR(255) NULL,
  created_by VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_person_day_period (person_id, day, period),
  FOREIGN KEY (person_id) REFERENCES people(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- weekday follows ISO-8601: 1=Monday .. 7=Sunday.
CREATE TABLE IF NOT EXISTS person_recurring_unavailability (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  person_id      INT UNSIGNED NOT NULL,
  weekday        TINYINT UNSIGNED NOT NULL,
  interval_weeks TINYINT UNSIGNED NOT NULL DEFAULT 1,
  anchor_date    DATE NULL,
  period         ENUM('all_day','am','pm') NOT NULL DEFAULT 'all_day',
  reason         VARCHAR(255) NULL,
  created_by     VARCHAR(255) NULL,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_person_weekday_period (person_id, weekday, period),
  FOREIGN KEY (person_id) REFERENCES people(id) ON DELETE CASCADE
) ENGINE=InnoDB;

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

CREATE TABLE IF NOT EXISTS shot_lists (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_id  INT UNSIGNED NOT NULL UNIQUE,
  subtitle    VARCHAR(255) NULL,
  sections    JSON NULL,
  created_by  VARCHAR(255) NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
) ENGINE=InnoDB;

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

CREATE TABLE IF NOT EXISTS app_users (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email          VARCHAR(255) NOT NULL UNIQUE,
  name           VARCHAR(255) NULL,
  last_login_at  TIMESTAMP NULL,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
