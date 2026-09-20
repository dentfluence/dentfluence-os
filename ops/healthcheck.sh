#!/usr/bin/env bash
# =============================================================================
# Dentfluence — hourly health check.
# -----------------------------------------------------------------------------
# Nobody was watching failed_jobs, nothing noticed if the scheduler loop died,
# and a 0-byte backup went unseen for days. This is the thing that watches.
#
# Run from cron:  0 * * * * cd /opt/dentfluence && ./ops/healthcheck.sh
# Run by hand:    ./ops/healthcheck.sh            (add --verbose to see passes)
#
# Every check reports through notify() in ops/alerts.sh, which logs always and
# sends only on a state change. Safe to run as often as you like.
# =============================================================================
cd "$(dirname "$0")/.." || exit 1
. ./ops/alerts.sh

ENV_FILE=".env.production"
COMPOSE="docker compose --env-file ${ENV_FILE}"
SITE="${HEALTH_URL:-https://srv1791841.hstgr.cloud/login}"
VERBOSE=0; [ "${1:-}" = "--verbose" ] && VERBOSE=1
say () { [ "$VERBOSE" = "1" ] && echo "$1"; return 0; }

# --- 1. containers -----------------------------------------------------------
EXPECTED="app nginx mysql queue scheduler gotenberg"
MISSING=""
for c in $EXPECTED; do
  docker ps --format '{{.Names}}' | grep -q "dentfluence-${c}-1" || MISSING="${MISSING} ${c}"
done
if [ -n "$MISSING" ]; then notify CRIT containers "container(s) not running:${MISSING}"
else notify OK containers "all 6 containers running"; say "  ok containers"; fi

# --- 2. mysql health ---------------------------------------------------------
MH=$(docker inspect --format '{{.State.Health.Status}}' dentfluence-mysql-1 2>/dev/null)
if [ "$MH" = "healthy" ]; then notify OK mysql "mysql healthy"; say "  ok mysql"
else notify CRIT mysql "mysql health is '${MH:-unknown}'"; fi

# --- 3. the site actually answers --------------------------------------------
CODE=$(curl -sS -m 20 -o /dev/null -w '%{http_code}' "$SITE" 2>/dev/null)
if [ "$CODE" = "200" ]; then notify OK site "login page returns 200"; say "  ok site"
else notify CRIT site "login page returned '${CODE:-no response}'"; fi

# --- 4. TLS certificate ------------------------------------------------------
HOSTPART=$(echo "$SITE" | sed -E 's#https?://([^/]+).*#\1#')
END=$(echo | openssl s_client -connect "${HOSTPART}:443" -servername "$HOSTPART" 2>/dev/null \
      | openssl x509 -noout -enddate 2>/dev/null | cut -d= -f2)
if [ -n "$END" ]; then
  DAYS=$(( ( $(date -d "$END" +%s) - $(date +%s) ) / 86400 ))
  if   [ "$DAYS" -lt 7 ];  then notify CRIT tls "TLS certificate expires in ${DAYS} days"
  elif [ "$DAYS" -lt 14 ]; then notify WARN tls "TLS certificate expires in ${DAYS} days"
  else notify OK tls "TLS certificate valid for ${DAYS} more days"; say "  ok tls (${DAYS}d)"; fi
else
  notify WARN tls "could not read the TLS certificate"
fi

# --- 5. failed jobs ----------------------------------------------------------
FJ=$($COMPOSE exec -T app php artisan queue:failed --json < /dev/null 2>/dev/null | jq 'length' 2>/dev/null)
[ -z "$FJ" ] && FJ=$($COMPOSE exec -T app php artisan queue:failed < /dev/null 2>/dev/null | grep -c '^| ' )
if [ "${FJ:-0}" -gt 0 ] 2>/dev/null; then notify WARN failed_jobs "${FJ} failed job(s) in the queue"
else notify OK failed_jobs "no failed jobs"; say "  ok failed_jobs"; fi

# --- 6. the scheduler loop is alive ------------------------------------------
LAST=$($COMPOSE logs --tail 1 --timestamps scheduler 2>/dev/null | grep -oE '[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9:]{8}' | tail -1)
if [ -n "$LAST" ]; then
  AGE=$(( ( $(date +%s) - $(date -d "${LAST}Z" +%s) ) / 60 ))
  if [ "$AGE" -gt 20 ]; then notify CRIT scheduler "scheduler has not logged for ${AGE} minutes"
  else notify OK scheduler "scheduler ran ${AGE} min ago"; say "  ok scheduler (${AGE}m)"; fi
else
  notify WARN scheduler "could not read the scheduler log"
fi

# --- 7. local backup freshness ----------------------------------------------
NEW=$(ls -1t backups/db_*.sql.gz 2>/dev/null | head -1)
if [ -n "$NEW" ]; then
  AGE=$(( ( $(date +%s) - $(stat -c%Y "$NEW") ) / 3600 ))
  if [ "$AGE" -gt 26 ]; then notify CRIT backup_local "newest local backup is ${AGE} hours old"
  else notify OK backup_local "local backup ${AGE}h old"; say "  ok backup_local (${AGE}h)"; fi
else
  notify CRIT backup_local "there is no local database backup at all"
fi

# --- 8. off-site backup freshness -------------------------------------------
if rclone listremotes 2>/dev/null | grep -q '^offsite:'; then
  RNEW=$(rclone lsf offsite:daily --format "tp" < /dev/null 2>/dev/null | grep 'db_' | sort -r | head -1 | cut -d';' -f1)
  if [ -n "$RNEW" ]; then
    AGE=$(( ( $(date +%s) - $(date -d "${RNEW}" +%s) ) / 3600 ))
    if [ "$AGE" -gt 26 ]; then notify CRIT backup_offsite "newest off-site backup is ${AGE} hours old"
    else notify OK backup_offsite "off-site backup ${AGE}h old"; say "  ok backup_offsite (${AGE}h)"; fi
  else
    notify CRIT backup_offsite "no off-site database backup found"
  fi
else
  notify CRIT backup_offsite "no 'offsite' rclone remote is configured"
fi

# --- 9. disk -----------------------------------------------------------------
USE=$(df --output=pcent / | tail -1 | tr -dc '0-9')
if   [ "$USE" -ge 92 ]; then notify CRIT disk "root filesystem ${USE}% full"
elif [ "$USE" -ge 85 ]; then notify WARN disk "root filesystem ${USE}% full"
else notify OK disk "disk ${USE}% used"; say "  ok disk (${USE}%)"; fi

# --- 10. containers that keep restarting -------------------------------------
# An absolute count is useless here: the queue worker runs with --max-time=3600,
# so it exits cleanly and is restarted once an hour BY DESIGN (19 restarts in 19
# hours on 19 Sep, all healthy). What matters is the RATE, so compare against
# the count this check saw last time and alert only on an unusual jump.
RC_DIR="${ALERT_STATE_DIR}/restartcount"
mkdir -p "$RC_DIR"
for c in app queue scheduler nginx mysql; do
  RC=$(docker inspect --format '{{.RestartCount}}' "dentfluence-${c}-1" 2>/dev/null)
  [ -z "$RC" ] && continue
  PREVF="${RC_DIR}/${c}"
  PREV=$(cat "$PREVF" 2>/dev/null || echo "$RC")
  echo "$RC" > "$PREVF"
  DELTA=$(( RC - PREV ))
  [ "$DELTA" -lt 0 ] && DELTA=0          # container was recreated, counter reset
  if [ "$DELTA" -ge 3 ]; then
    notify WARN "restarts_${c}" "${c} restarted ${DELTA} times in the last hour (total ${RC})"
  else
    notify OK "restarts_${c}" "${c} restart rate normal (${DELTA}/hour, total ${RC})"
    say "  ok restarts_${c} (${DELTA}/h)"
  fi
done

say "healthcheck complete"
exit 0
