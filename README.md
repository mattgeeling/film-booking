# Film Plan

Internal shoot scheduling tool: a week-view booking board that replaces the
Google Doc grid. Pencil (tentative) bookings are red; confirmed bookings are
green and get posted to attendees' real Google Calendars (Milestone 3).

## Local development

Requires PHP, Composer, and MySQL (installed via `brew install php composer mysql`).

```bash
brew services start mysql
mysql -u root -e "
  CREATE DATABASE IF NOT EXISTS film_plan CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  CREATE USER IF NOT EXISTS 'film_plan'@'localhost' IDENTIFIED BY 'film_plan_dev';
  GRANT ALL PRIVILEGES ON film_plan.* TO 'film_plan'@'localhost';
"
mysql -u film_plan -pfilm_plan_dev film_plan < db/schema.sql
mysql -u film_plan -pfilm_plan_dev film_plan < db/seed.sql   # replace placeholder emails first

php -S localhost:8000 -t public
```

Then open http://localhost:8000/.

`config/config.php` (gitignored) holds local DB credentials and Google
settings; `config/config.example.php` is the committed template. Production
gets its own `config/config.php` created directly on the IONOS server.

## Status

- **Milestone 1 (done):** week view, pencil bookings (create/edit/cancel), no Google integration yet.
- **Milestone 2:** people management UI.
- **Milestone 3:** Google Calendar sync on Confirm.
- **Milestone 4:** Google Sign-In auth.
- **Milestone 5:** polish + deploy to IONOS.

See `/Users/mattgeeling/.claude/plans/crispy-weaving-backus.md` for the full plan.
