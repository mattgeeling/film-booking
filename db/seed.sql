-- Matt's real address is used for now so Calendar-sync testing (Milestone 3)
-- can be verified against a real inbox before it ever touches Tom's/Nigel's
-- actual calendars. Tom and Nigel are placeholders (unique emails required by
-- the schema) — swap in their real Workspace addresses once known, or just
-- edit them via the People screen once Milestone 2 is built.
INSERT INTO people (name, email, active) VALUES
  ('Matt Geeling (test)', 'matt@fuzzyduck.co.uk', 1),
  ('Tom Ellison', 'tom@REPLACE-ME.example', 1),
  ('Nigel Moore', 'nigel@REPLACE-ME.example', 1);
