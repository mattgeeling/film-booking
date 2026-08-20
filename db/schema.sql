CREATE TABLE IF NOT EXISTS people (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(100)  NOT NULL,
  role       VARCHAR(100)  NULL,
  email      VARCHAR(255)  NOT NULL UNIQUE,
  active     TINYINT(1)    NOT NULL DEFAULT 1,
  created_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS bookings (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title          VARCHAR(255) NOT NULL,
  location       VARCHAR(255) NULL,
  notes          TEXT NULL,
  start_datetime DATETIME NOT NULL,
  end_datetime   DATETIME NOT NULL,
  status         ENUM('pencil','confirmed','cancelled') NOT NULL DEFAULT 'pencil',
  created_by     VARCHAR(255) NULL,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  confirmed_at   TIMESTAMP NULL,
  cancelled_at   TIMESTAMP NULL,
  INDEX idx_start (start_datetime),
  INDEX idx_status (status)
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

CREATE TABLE IF NOT EXISTS app_users (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email          VARCHAR(255) NOT NULL UNIQUE,
  name           VARCHAR(255) NULL,
  last_login_at  TIMESTAMP NULL,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
