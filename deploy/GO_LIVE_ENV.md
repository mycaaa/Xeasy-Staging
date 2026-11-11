# Settings for the first weeks on live (decided 6 Nov 2026)

Start the new controls in log-only mode, read the logs with Finance, then switch to enforce.

| .env key | Live start value | Later | What it does |
|---|---|---|---|
| CLAIM_TRANSITIONS_ENFORCE | false | true | Claim status gate (B-01/L-01). false = allowed but logged ("Claim status change refused ... enforced=false") |
| PAYMENT_MAKER_CHECKER | true | true | Maker-checker on expense payments (LV-C05) |
| PAYMENT_MAKER_CHECKER_MODE | log | enforce | log = same person may approve and release, but each case is logged |
| API_AUTH_ENFORCE / MOBILE_API_AUTH_ENFORCE | false | true after the new app is out | Login token on the API and mobile API |
| ROUTE_ACCESS_ENFORCE / RECORD_ACCESS_ENFORCE | true | true | Role and record checks (SEC-02/03); false only to roll back |
| AUTH_LOCAL_PASSWORD_LOGIN | false | false | HRIMS password only (AUTH-HRIMS-01). true only until the 4 guest accounts (4685, 4686, 4687, 4773) have HRIMS logins |
| MAIL_ALLOWED_DOMAINS | (empty) | decision | G-08: e.g. vnrseeds.com,vnrseeds.in,vnrnursery.in = mail only to company addresses. Most staff have a Gmail address in HRIMS (1,389 of about 1,600 active), so set it only after HR fills company addresses |
| CLAIM_RULES_LOCAL_LIMITS | false | false | E-03: limits only from the HRIMS eligibility; true brings back the old local claimlimits table |
| ODOMETER_OCR_URL | (default) | - | G-02: odometer reading service; listed in docs/INTEGRATIONS.md |
| CLAIM_FILES_LOCAL | never set on live | - | TEST-S3-01: test servers without the S3 bucket only |
| VEHICLE_RATE_REPORT_TO | HR mailbox (to be given by HR) | - | VEH-RATE-ALERT-01: zero-rate mails go here, every Super Admin in Cc; without it the mails are logged as not sent |
| OWN_CAR_VALUE_EXEMPT | (default 619,1745,1813) | empty once HR fills the values | OWN-CAR-VALUE-01: Own Your Car 4W km without a vehicle value get rate 0, except these employees (748, 1506, 1568) |
| REVIEW_* | (defaults) | Finance | F-02/F-06/F-08 thresholds of the Finance review flags screen (config/claim_rules.php review_flags) |

Rule decided 6 Nov: a VNR claim must be Verified before it can be Approved (config/claim_transitions.php).
In log-only mode this shows up as logged refusals until the gate is enforced.

## Scheduler on live (SCHED-01)

Add this one line to the crontab of the web server user on the live app server (path = the live Laravel folder):

```
* * * * * cd /path/to/live/laravel && php artisan schedule:run >> storage/logs/scheduler.log 2>&1
```

It runs the jobs listed by `php artisan schedule:list` (auto-verify hourly, back-dated odometer flag hourly,
claim-update notices, courier/approval/resigned reminders daily, VVNR hierarchy summary on the 10th, imprest automation
Mondays, month closing on the 15th, and the queue worker every minute). Check after 5 minutes: the log shows
"scheduler-heartbeat DONE" every minute. On staging this runs since 5 Nov with VVNR pointed away (read-only copy).

## Added 10 Nov 2026

- Migration `2026_11_10_000060_finance_review_flags_menu` (menu entry). Run with `--path`, like the other 10 Nov migrations.
- `claims:rate` runs nightly at 04:10 for VNR and VVNR (M-06). The first run takes about 2.5 minutes and fills the ratings for the year so far.
- After the old PHP system and the old API are switched off and their folders removed: `php artisan db:archive-dead-tables` (list), then
  `--apply` (D-11). Each table is saved to storage/app/db_archive/{database}/ before it is dropped. On staging 12 tables were archived and dropped.
- Backups (G-04): install `scripts/backup/xeasy_backup.sh` with /etc/xeasy-backup.conf, the key file and a backup-only MySQL user
  (instructions at the top of the script); cron nightly backup and weekly `--restore-test`; keep the key in the IT vault.

## Added 11 Nov 2026

- Migration `2026_11_11_000010_vehicle_rate_alerts` (alert and mail-log tables). `vehicle-rates:alert` runs daily at 09:20 for VNR and VVNR:
  the first run on live mails HR once for every active employee whose rate is zero (33 on staging), then every 3 days until the rate is entered.
- D-09 zero dates: the code reads NULL and 0000-00-00 alike (`App\Support\ZeroDate`), so it can go live first. The data step
  (`php artisan db:normalize-year --year=N --apply`, one run per year, newest first) turns 0000-00-00 into NULL and converts the
  year tables to utf8mb4 (ROW_FORMAT=DYNAMIC first; each table is copied to `{table}_bak_YYYYMMDD` before it changes). Run it
  only after the old PHP system has stopped writing to the database (it still writes 0000-00-00). Staging: zero dates are NULL in years 3 to 8; the
  utf8mb4 conversion of y6/y7_expenseclaims (230k+ rows) stopped on 'Temp file write failure' because MySQL's tmpdir
  (/tmp, 3.6 GB tmpfs) was full. On live give MySQL a tmpdir with several GB free before this step (D-10).
  `LEGACY_ZERO_DATES` (default true) keeps "0000-00-00" in the mobile API's old-style date keys for the app in the stores;
  set it to false once the app reads the v2 envelope.
- LV-U03: `X-Xeasy-Response: v2` (or `?response=v2`) gives one JSON envelope on every `/api` route; nothing changes for
  callers that do not send it. See `docs/API_RESPONSE.md`.
- U-05: pop-up, frame, print and vendor-portal screens use `layouts.standalone`; nothing to set.
