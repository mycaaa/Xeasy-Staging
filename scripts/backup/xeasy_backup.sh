#!/usr/bin/env bash
# G-04 (10 Nov 2026): encrypted backup of every Xeasy database, kept off-site, with a restore test.
#
# The audit found one database backed up, unencrypted, never restored, and a restore script that drops data.
# This script:
#   1. dumps each database in XEASY_BACKUP_DBS (consistent, with routines and triggers) with mysqldump,
#   2. compresses and encrypts each dump (AES-256, key file readable only by the backup user),
#   3. copies the files off the server (XEASY_BACKUP_REMOTE, an rsync or rclone target), keeps KEEP_DAYS locally,
#   4. with --restore-test, loads the newest dump of each database into a scratch database (<db>_restore_test),
#      compares table and row counts with the source, writes the result to the log and drops the scratch
#      database. It never writes to the source database.
#
# Settings (in /etc/xeasy-backup.conf, mode 600, never in git):
#   XEASY_BACKUP_DBS="expense expense_nursery expense_vvnr hrims"
#   XEASY_BACKUP_DIR=/var/backups/xeasy
#   XEASY_BACKUP_KEY=/etc/xeasy-backup.key          (openssl rand -base64 48 > key; chmod 600; keep a copy in the IT vault)
#   XEASY_BACKUP_MYCNF=/etc/xeasy-backup.my.cnf     ([client] user/password of a backup-only MySQL user: SELECT, SHOW VIEW,
#                                                    TRIGGER, LOCK TABLES, EVENT, PROCESS; plus CREATE/DROP/INSERT on *_restore_test)
#   XEASY_BACKUP_REMOTE="rclone:xeasy-backups/"     or "backup@host:/srv/xeasy/" (rsync over ssh); empty = no copy
#   KEEP_DAYS=14
#
# Cron (IT): 30 1 * * *  /opt/xeasy/xeasy_backup.sh            nightly backup
#            30 4 * * 0  /opt/xeasy/xeasy_backup.sh --restore-test   weekly restore test
set -euo pipefail
CONF=${XEASY_BACKUP_CONF:-/etc/xeasy-backup.conf}
[ -r "$CONF" ] && . "$CONF"
: "${XEASY_BACKUP_DBS:?set XEASY_BACKUP_DBS}" "${XEASY_BACKUP_DIR:?set XEASY_BACKUP_DIR}" "${XEASY_BACKUP_KEY:?set XEASY_BACKUP_KEY}" "${XEASY_BACKUP_MYCNF:?set XEASY_BACKUP_MYCNF}"
KEEP_DAYS=${KEEP_DAYS:-14}
STAMP=$(date +%Y%m%d_%H%M%S)
LOG="$XEASY_BACKUP_DIR/backup.log"
DRY=${DRY_RUN:-0}
mkdir -p "$XEASY_BACKUP_DIR"; chmod 700 "$XEASY_BACKUP_DIR"
log() { echo "$(date '+%F %T') $*" | tee -a "$LOG"; }
run() { if [ "$DRY" = 1 ]; then echo "DRY: $*"; else eval "$@"; fi; }
[ -r "$XEASY_BACKUP_KEY" ] || { log "key file missing"; exit 1; }

counts() { # db -> "table rows" lines (exact counts)
  mysql --defaults-extra-file="$XEASY_BACKUP_MYCNF" -N -e "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='$1' AND TABLE_TYPE='BASE TABLE' ORDER BY 1" |
  while read -r t; do echo "$t $(mysql --defaults-extra-file="$XEASY_BACKUP_MYCNF" -N -e "SELECT COUNT(*) FROM \`$1\`.\`$t\`")"; done
}

if [ "${1:-}" = "--restore-test" ]; then
  rc=0
  for db in $XEASY_BACKUP_DBS; do
    f=$(ls -1t "$XEASY_BACKUP_DIR/${db}_"*.sql.gz.enc 2>/dev/null | head -1 || true)
    [ -n "$f" ] || { log "RESTORE-TEST $db: no backup file"; rc=1; continue; }
    scratch="${db}_restore_test"
    run "mysql --defaults-extra-file='$XEASY_BACKUP_MYCNF' -e 'DROP DATABASE IF EXISTS \`$scratch\`; CREATE DATABASE \`$scratch\`'"
    run "openssl enc -d -aes-256-cbc -pbkdf2 -iter 200000 -pass file:'$XEASY_BACKUP_KEY' -in '$f' | gunzip | mysql --defaults-extra-file='$XEASY_BACKUP_MYCNF' '$scratch'"
    if [ "$DRY" != 1 ]; then
      # the source has moved on since the dump, so compare table lists exactly and rows only as "not fewer than 90%"
      diff <(counts "$db" | cut -d' ' -f1) <(counts "$scratch" | cut -d' ' -f1) >/dev/null && same_tables=yes || same_tables=no
      src=$(counts "$db" | awk '{s+=$2} END {print s+0}'); dst=$(counts "$scratch" | awk '{s+=$2} END {print s+0}')
      if [ "$same_tables" = yes ] && [ "$dst" -ge $(( src * 9 / 10 )) ]; then log "RESTORE-TEST $db OK: $(basename "$f"), rows $dst (source now $src)"; else log "RESTORE-TEST $db FAILED: tables same=$same_tables rows $dst vs source $src"; rc=1; fi
    fi
    run "mysql --defaults-extra-file='$XEASY_BACKUP_MYCNF' -e 'DROP DATABASE IF EXISTS \`$scratch\`'"
  done
  exit $rc
fi

for db in $XEASY_BACKUP_DBS; do
  out="$XEASY_BACKUP_DIR/${db}_${STAMP}.sql.gz.enc"
  run "mysqldump --defaults-extra-file='$XEASY_BACKUP_MYCNF' --single-transaction --quick --routines --triggers --events --hex-blob '$db' 2>>'$LOG' | gzip -6 | openssl enc -aes-256-cbc -salt -pbkdf2 -iter 200000 -pass file:'$XEASY_BACKUP_KEY' -out '$out'"
  [ "$DRY" = 1 ] || { chmod 600 "$out"; log "backup $db -> $(basename "$out") $(du -h "$out" | cut -f1)"; }
done
if [ -n "${XEASY_BACKUP_REMOTE:-}" ]; then
  case "$XEASY_BACKUP_REMOTE" in
    rclone:*) run "rclone copy '$XEASY_BACKUP_DIR' '${XEASY_BACKUP_REMOTE#rclone:}' --include '*_${STAMP}.sql.gz.enc'" ;;
    *)        run "rsync -a --include='*_${STAMP}.sql.gz.enc' --exclude='*' '$XEASY_BACKUP_DIR/' '$XEASY_BACKUP_REMOTE'" ;;
  esac
  log "copied off-site to $XEASY_BACKUP_REMOTE"
else
  log "WARNING: XEASY_BACKUP_REMOTE not set, backup is on this server only"
fi
run "find '$XEASY_BACKUP_DIR' -name '*.sql.gz.enc' -mtime +$KEEP_DAYS -delete"
