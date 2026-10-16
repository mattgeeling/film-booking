-- Each booking can name its Synergist job, so Job Health can put confirmed
-- shoots on its schedule against that job's Filming phase.
ALTER TABLE bookings
  ADD COLUMN synergist_job_number VARCHAR(20) NULL AFTER client_id,
  ADD INDEX idx_synergist_job (synergist_job_number);
