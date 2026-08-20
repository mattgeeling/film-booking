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

- **Milestone 1 (done):** week view, pencil bookings (create/edit/cancel).
- **Milestone 2 (done):** people management UI.
- **Milestone 3 (done):** Google Calendar sync on Confirm.
- **Milestone 4 (done):** Google Sign-In auth, restricted to the Fuzzy Duck Workspace domain.
- **Milestone 5 (in progress):** deploy to IONOS.

See `/Users/mattgeeling/.claude/plans/crispy-weaving-backus.md` for the full plan.

## Deployment

Every push to `main` deploys automatically to IONOS via `.github/workflows/deploy.yml`
(GitHub Actions → FTP). This requires the following **repository secrets**
(Settings → Secrets and variables → Actions → New repository secret):

| Secret | Value |
|---|---|
| `IONOS_FTP_SERVER` | FTP host from the IONOS control panel |
| `IONOS_FTP_USERNAME` | FTP username |
| `IONOS_FTP_PASSWORD` | FTP password |
| `IONOS_FTP_SERVER_DIR` | Target directory on the server (e.g. `/` or `/film-booking/`) |
| `DB_HOST` | MySQL host from IONOS (often `localhost` or a specific hostname) |
| `DB_NAME` | MySQL database name |
| `DB_USER` | MySQL username |
| `DB_PASS` | MySQL password — avoid single quotes (`'`) in this value |
| `GOOGLE_OAUTH_CLIENT_ID` | The `....apps.googleusercontent.com` client ID |
| `WORKSPACE_DOMAIN` | `fuzzyduck.co.uk` |
| `GOOGLE_SERVICE_ACCOUNT_JSON` | Paste the **entire contents** of the service account JSON key file |

The workflow writes `config/config.php` and `config/service-account.json` from
these secrets at deploy time — neither file is ever committed to git.

**One-time manual steps** (not automated):
1. Create the MySQL database in the IONOS control panel.
2. Import the schema once via phpMyAdmin: run `db/schema.sql`, then `db/seed.sql`
   (after replacing the placeholder Tom/Nigel emails, or just add people via
   the People screen once the app is live).
3. Confirm HTTPS is active on the domain (required for Google Sign-In).
4. In Google Cloud Console, add the production domain to the OAuth client's
   Authorized JavaScript origins (it currently only allows `localhost:8000`).
