#!/usr/bin/env bash
# Xeasy — one-command install / update for a Linux server (Apache or nginx + PHP-FPM, MySQL/MariaDB).
#
#   First install:   deploy/deploy.sh --first-run      (creates .env from .env.example, then stops so you fill it)
#                    deploy/deploy.sh --first-run      (again, after .env is filled: installs everything)
#   Every update:    deploy/deploy.sh                  (git pull, packages, migrations, caches, restart)
#
# Options:
#   --first-run       create .env / APP_KEY / storage link on a new server
#   --no-pull         use the files as they are (no git pull)
#   --skip-migrate    do not run migrations
#   --backup          run scripts/backup/xeasy_backup.sh before migrating (needs /etc/xeasy-backup.conf)
#   --yes             do not ask for confirmation
#   --plesk           run from Plesk Git "additional deployment actions" (implies --no-pull --yes; Plesk has already
#                     put the new files in place). See deploy/PLESK_AUTO_DEPLOY.md.
#
# Environment overrides: PHP=/usr/bin/php8.3  COMPOSER=/usr/local/bin/composer  WEB_USER=www-data  BRANCH=main
#
# Safe by design: only the migrations listed in deploy/migrations.txt run (each with --path); no data is changed
# beyond them. The one-off data steps (dates to NULL, archive of dead tables, ...) are listed in deploy/README.md
# and are run by hand when decided.
set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$APP_DIR"
# DEPLOY-AUTO-01: on Plesk the PHP binaries live in /opt/plesk/php/<ver>/bin and the site runs as the subscription user
if [ -z "${PHP:-}" ]; then
  PHP=php
  for v in 8.4 8.3 8.2; do [ -x "/opt/plesk/php/$v/bin/php" ] && { PHP="/opt/plesk/php/$v/bin/php"; break; }; done
fi
if [ -z "${COMPOSER:-}" ]; then
  if command -v composer >/dev/null; then COMPOSER=composer
  elif [ -f /usr/lib/plesk-9.0/composer.phar ]; then COMPOSER="$PHP /usr/lib/plesk-9.0/composer.phar"
  elif [ -f "$HOME/composer.phar" ]; then COMPOSER="$PHP $HOME/composer.phar"
  else COMPOSER=composer; fi
fi
if [ -z "${WEB_USER:-}" ]; then
  if [ -d /usr/local/psa ] || ! id www-data >/dev/null 2>&1; then WEB_USER="$(id -un)"; else WEB_USER=www-data; fi
fi
BRANCH="${BRANCH:-}"
FIRST=0; PULL=1; MIGRATE=1; BACKUP=0; YES=0
for a in "$@"; do
  case "$a" in
    --first-run) FIRST=1 ;; --no-pull) PULL=0 ;; --skip-migrate) MIGRATE=0 ;; --backup) BACKUP=1 ;; --yes) YES=1 ;;
    --plesk) PULL=0; YES=1 ;;
    -h|--help) sed -n '2,22p' "$0"; exit 0 ;;
    *) echo "Unknown option $a"; exit 2 ;;
  esac
done

# two pushes close together: the second deploy waits for the first instead of running over it
LOCK="$APP_DIR/storage/framework/deploy.lock"
mkdir -p "$APP_DIR/storage/framework" 2>/dev/null || true
[ -w "$APP_DIR/storage/framework" ] || LOCK="/tmp/xeasy-deploy-$(id -un).lock"
exec 9>"$LOCK"
command -v flock >/dev/null && { flock -w 600 9 || { echo "STOP: another deploy is still running" >&2; exit 1; }; }

step() { printf '\n\033[1m== %s\033[0m\n' "$*"; }
fail() { printf '\033[31mSTOP: %s\033[0m\n' "$*" >&2; exit 1; }
warn() { printf '\033[33mNote: %s\033[0m\n' "$*" >&2; }
art()  { "$PHP" artisan "$@"; }
envval() { grep -E "^$1=" .env | tail -1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//'; }

# ---------------------------------------------------------------- 1. checks
step "Checks"
command -v "$PHP" >/dev/null || fail "PHP not found ($PHP)"
"$PHP" -r 'exit(version_compare(PHP_VERSION, "8.2.0", ">=") ? 0 : 1);' || fail "PHP 8.2 or newer needed (found $("$PHP" -r 'echo PHP_VERSION;'))"
missing=""
for e in pdo_mysql mbstring xml curl zip gd intl bcmath fileinfo openssl tokenizer ctype dom; do
  "$PHP" -m | grep -qi "^$e$" || missing="$missing $e"
done
[ -z "$missing" ] || fail "PHP extensions missing:$missing (e.g. apt install php-<name>)"
command -v ${COMPOSER%% *} >/dev/null || fail "composer not found (https://getcomposer.org/download/)"
command -v git >/dev/null || PULL=0
echo "PHP $("$PHP" -r 'echo PHP_VERSION;'), app folder $APP_DIR, web user $WEB_USER"

if [ ! -f .env ]; then
  if [ "$FIRST" = 1 ]; then
    cp .env.example .env
    chmod 640 .env
    fail ".env created from .env.example. Fill every <fill> value (databases, keys), then run: deploy/deploy.sh --first-run"
  fi
  fail ".env missing. New server? run deploy/deploy.sh --first-run"
fi
grep -q '<fill' .env && fail ".env still has <fill> values: $(grep -o '^[A-Z_]*=<fill' .env | cut -d= -f1 | tr '\n' ' ')"
[ "$(envval APP_ENV)" = "production" ] && [ "$YES" = 0 ] && {
  read -r -p "APP_ENV=production. This script is meant for the test host first. Continue? [y/N] " a; [ "$a" = y ] || exit 1; }

# ---------------------------------------------------------------- 2. code
if [ "$PULL" = 1 ] && [ -d .git ]; then
  step "Getting the latest code"
  [ -n "$(git status --porcelain --untracked-files=no)" ] && fail "Local changes in tracked files; commit or discard them first (git status)"
  [ -n "$BRANCH" ] && git checkout "$BRANCH"
  git pull --ff-only
  git log -1 --format='Now at %h %s (%ci)'
fi

# ---------------------------------------------------------------- 3. maintenance mode
LIVE_BEFORE=0
if [ "$FIRST" = 0 ] && [ -f vendor/autoload.php ]; then
  step "Maintenance mode on"
  art down --retry=30 || true
  LIVE_BEFORE=1
fi
trap '[ "$LIVE_BEFORE" = 1 ] && "$PHP" artisan up >/dev/null 2>&1; echo "Deploy stopped; site is back up (check the message above)."' ERR

# ---------------------------------------------------------------- 4. packages
step "PHP packages (composer, no dev packages)"
$COMPOSER install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress

# ---------------------------------------------------------------- 5. folders and permissions
step "Folders and permissions"
# FILES-PERM-01 (13 Nov 2026): bill / odometer files live in storage/app/s3_local (CLAIM_FILES_LOCAL=true), not in a
# top-level s3_local folder. The web user must be able to create sub-folders there, and files written by this
# script (logs, compiled views) must stay writable by the web user: group = web user, set-group-id folders, umask 002.
umask 002
mkdir -p storage/app/public storage/app/s3_local storage/framework/{cache,sessions,views,testing} storage/logs bootstrap/cache
if [ "$(id -u)" = 0 ] || id -nG | grep -qw "$WEB_USER"; then
  chgrp -R "$WEB_USER" storage bootstrap/cache 2>/dev/null || warn "could not set group $WEB_USER on storage (run once as root)"
else
  warn "this user is not in group $WEB_USER: the web server may not be able to write storage/ (uploads fail). Run once as root, or add this user to $WEB_USER."
fi
chmod -R ug+rwX storage bootstrap/cache
find storage bootstrap/cache -type d -exec chmod g+s {} + 2>/dev/null || true
chmod 2770 storage/app/s3_local
chmod 640 .env
[ -e public/storage ] || art storage:link

if [ -z "$(envval APP_KEY)" ]; then
  [ "$FIRST" = 1 ] || fail "APP_KEY is empty; run with --first-run"
  step "Application key"
  art key:generate --force
fi

# ---------------------------------------------------------------- 6. database
step "Database connection"
art migrate:status >/dev/null 2>&1 || art db:show >/dev/null || fail "Cannot reach the database in .env (DB_HOST / DB_DATABASE / DB_USERNAME)"
echo "OK"

if [ "$MIGRATE" = 1 ]; then
  if [ "$BACKUP" = 1 ]; then
    step "Backup before migrating"
    bash scripts/backup/xeasy_backup.sh || fail "backup failed; nothing was migrated"
  fi
  step "Migrations (only those in deploy/migrations.txt)"
  pending=0
  while read -r m; do
    [ -z "$m" ] || [ "${m#\#}" != "$m" ] && continue
    f="database/migrations/$m.php"
    [ -f "$f" ] || fail "listed migration not found: $f"
    out="$(art migrate --force --path="$f" 2>&1)" || { echo "$out"; fail "migration $m failed"; }
    if echo "$out" | grep -q "DONE"; then echo "  ran  $m"; pending=$((pending+1)); fi
  done < deploy/migrations.txt
  echo "$pending migration(s) ran; the rest were already in place."
fi

# ---------------------------------------------------------------- 7. caches
step "Caches"
art optimize:clear
# config:cache and route:cache are NOT used: about 25 places still read env() directly, which returns null
# once the configuration is cached. view:cache compiles every Blade view (also a syntax check).
art view:cache
art queue:restart || true

# ---------------------------------------------------------------- 8. PHP-FPM reload (clears OPcache)
for svc in $(systemctl list-units --type=service --no-legend 2>/dev/null | awk '{print $1}' | grep -E '^php[0-9.]*-fpm' || true); do
  if sudo -n systemctl reload "$svc" 2>/dev/null; then echo "reloaded $svc"; else warn "reload $svc by hand (sudo systemctl reload $svc) so PHP picks up the new code"; fi
done

# ---------------------------------------------------------------- 9. back up
if [ "$LIVE_BEFORE" = 1 ]; then step "Maintenance mode off"; art up; fi
trap - ERR

# ---------------------------------------------------------------- 10. checks after deploy
step "Checks after deploy"
URL="$(envval APP_URL)"
code="$(curl -s -o /dev/null -w '%{http_code}' --max-time 20 "$URL/up" || true)"
[ "$code" = 200 ] && echo "Health check $URL/up: 200 OK" || warn "Health check $URL/up answered '$code' (web server / DNS / TLS?)"
code="$(curl -s -o /dev/null -w '%{http_code}' --max-time 20 "$URL/login" || true)"
echo "Login page: $code"
crontab -l 2>/dev/null | grep -q "$APP_DIR.*schedule:run" || crontab -u "$WEB_USER" -l 2>/dev/null | grep -q "$APP_DIR.*schedule:run" || \
  warn "Scheduler not in cron yet. Add for user $WEB_USER:  * * * * * cd $APP_DIR && $PHP artisan schedule:run >> storage/logs/scheduler.log 2>&1"
# FILES-PERM-01: can the web server store a bill? (runs the check as the web user when possible)
if [ "$(id -un)" = "$WEB_USER" ]; then
  art files:s3-check || warn "claim files cannot be stored - uploads will fail (see above)"
elif sudo -n -u "$WEB_USER" true 2>/dev/null; then
  sudo -n -u "$WEB_USER" "$PHP" artisan files:s3-check || warn "claim files cannot be stored by $WEB_USER - uploads will fail (see above)"
else
  warn "could not run the file-storage check as $WEB_USER; run:  sudo -u $WEB_USER $PHP artisan files:s3-check"
fi
git log -1 --format='Deployed %h %s' 2>/dev/null || true
echo "Done."
