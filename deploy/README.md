# Deploying Xeasy to the test host (xeasy.vnrseeds.co.in)

This repository is the staging build of the new Xeasy app (Laravel 12), audited and fixed up to 11 Nov 2026.
It goes to **xeasy.vnrseeds.co.in for testing first**; after sign-off the same repository moves to the production server.

Everything runs from one script: `deploy/deploy.sh`.

## 1. What the server needs

| Item | Version / note |
|---|---|
| Linux | Ubuntu 22.04 or 24.04 |
| PHP-FPM + CLI | 8.2 or newer (staging runs 8.5). Extensions: pdo_mysql, mbstring, xml, curl, zip, gd, intl, bcmath, fileinfo, openssl, tokenizer, ctype, dom |
| Composer | 2.x |
| Web server | Apache 2.4 (sample: `deploy/apache-xeasy.vnrseeds.co.in.conf`) or nginx (`deploy/nginx-xeasy.vnrseeds.co.in.conf`) |
| Database | MySQL 8 or MariaDB 10.6+. Give MySQL a tmpdir with several GB free (large tables are converted by ALTER) |
| DNS / TLS | `xeasy.vnrseeds.co.in` pointing at the server; certificate with certbot |
| Git | access to this repository (deploy key or personal token of the IT account) |

## 2. Databases for the test host

The app uses separate databases. For testing, restore **copies** (fresh dumps of live), never point the test host
at the live databases:

| .env keys | Database | Note |
|---|---|---|
| DB_* and MOBILE_DB_* | VNR expense (`expense` on live) | read-write copy |
| DB_VVNR_* and MOBILE_VVNR_* | VVNR expense (`vnressus_expense` on live) | read-write copy |
| DB_HRIMS_* | HRIMS | copy; a read-only user is enough |

Use one MySQL user per database with rights on that database only (no root). The schema changes of this release are
applied by the script (step 4); the older tables already exist in the dumps.

## 3. First install

```bash
cd /var/www
git clone <repository url> xeasy
cd xeasy
deploy/deploy.sh --first-run          # creates .env from .env.example and stops
nano .env                             # fill every <fill>: databases, INTERNAL_API_KEY, CRON_KEY, SSO_JWT_KEY
deploy/deploy.sh --first-run          # installs packages, app key, storage link, migrations, caches, checks
```

Then, as root, once:

```bash
cp deploy/apache-xeasy.vnrseeds.co.in.conf /etc/apache2/sites-available/   # edit the path and PHP version in it
a2enmod rewrite headers proxy_fcgi setenvif ssl && a2ensite apache-xeasy.vnrseeds.co.in && systemctl reload apache2
certbot --apache -d xeasy.vnrseeds.co.in
crontab -u www-data -e                # add the line below
```

```
* * * * * cd /var/www/xeasy && php artisan schedule:run >> storage/logs/scheduler.log 2>&1
```

The scheduler also runs the queue worker (SCHEDULE_QUEUE_WORKER=true); no supervisor is needed.
`php artisan schedule:list` shows every job.

## 4. Every update

```bash
cd /var/www/xeasy && deploy/deploy.sh            # add --backup to take a database backup first
```

The script, in order:

1. checks PHP, the extensions, composer and `.env` (stops on any `<fill>` left);
2. `git pull --ff-only` (stops if someone edited files on the server);
3. puts the site in maintenance mode;
4. `composer install --no-dev`;
5. creates the storage folders and sets permissions for the web user;
6. checks the database connection and runs **only the migrations listed in `deploy/migrations.txt`**, one by one
   with `--path` (a migration that already ran is skipped);
7. clears caches and compiles the views (`config:cache` is deliberately not used, see the script);
8. reloads PHP-FPM so the new code is used;
9. takes the site out of maintenance mode;
10. checks `https://xeasy.vnrseeds.co.in/up` and the login page, and warns if the scheduler is not in cron.

If any step fails the script stops, brings the site back up and prints the reason.

To roll back: `git checkout <previous commit>` then `deploy/deploy.sh --no-pull --skip-migrate`.

## 5. Settings for testing

`.env.example` is set for the test host:

- mail is written to the log, not sent (`MAIL_MAILER=log`); for a planned mail test switch to smtp and keep
  `MAIL_ALLOWED_DOMAINS` so mail reaches company addresses only;
- bill and odometer images are stored on the server (`CLAIM_FILES_LOCAL=true`, folder `s3_local/`, served only to
  logged-in users); use a test S3 bucket instead if you have one, never the live bucket;
- bank payments (Matrix) are mocked, push notifications are off;
- the controls are enforced, so testers see what live will do once switched on. The live start values
  (log-only first) are in `deploy/GO_LIVE_ENV.md`.

## 6. One-off data steps (not run by the script)

These change data, so they are run by hand when decided, always first without `--apply` (dry run) to see the
count. They follow the cut-over runbook (approved 10 Nov 2026); on the test host run them in the same order to
rehearse:

| Command | What it does | Ref |
|---|---|---|
| `php artisan security:retire-legacy-logins` | blanks the old back-office MD5 passwords (HRIMS login only) | S-07 |
| `php artisan payments:mask-bank-data` | masks stored bank account numbers | |
| `php artisan files:make-private --dry-run` | moves uploaded files out of public folders. This one acts **without** a flag: list with `--dry-run` first | |
| `php artisan city-tiers:apply-policy` | city category A/B/C per policy | POL-01 |
| `php artisan claims:month-records --year=8` | creates missing month records for app-uploaded bills | MONTH-REC-01 |
| `php artisan runningkm:rebuild --year=8` | rebuilds running km after the odometer-edit bug | M-01 |
| `php artisan remarks:mark-auto` | marks auto-verified remarks | |
| `php artisan db:normalize-year --year=8` (then 7, 6 ...) | zero dates to NULL, utf8mb4; only after the old PHP system stops writing | D-09, D-10 |
| `php artisan db:archive-dead-tables` | archives and drops dead tables; only after the old system is removed | D-11 |

Add `--apply` to make the change. Each command prints what it changed; keep the output with the test records.

## 7. Never in this repository

`.env`, database dumps, uploaded files, logs, keys. `.gitignore` keeps them out; the GitHub workflow
(`.github/workflows/ci.yml`) checks syntax and scans for secrets on every push.
