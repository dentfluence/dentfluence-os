#!/usr/bin/env bash
# =============================================================================
# Dentfluence — the one place anything on this box raises an alarm.
# -----------------------------------------------------------------------------
# Source this, then call:
#     notify CRIT backup_offsite "last off-site backup is 31 hours old"
#     notify OK   backup_offsite "off-site backup is current"
#
# Three things it guarantees:
#   1. Every alert is written to backups/alerts.log, always, whatever else fails.
#   2. It does NOT spam. An alert is sent when the state CHANGES, and then at
#      most once every ALERT_REPEAT_HOURS while it is still bad. Recovery is
#      sent once.
#   3. It never takes the caller down. A dead channel is logged, not fatal.
#
# Channels come from /root/.dentfluence-alerts.conf (chmod 600, NOT in git).
# With no channel configured it still logs, so nothing is lost — see
# docs/ALERTING.md for how to switch one on.
# =============================================================================

ALERT_DIR="${ALERT_DIR:-/opt/dentfluence/backups}"
ALERT_LOG="${ALERT_LOG:-${ALERT_DIR}/alerts.log}"
ALERT_STATE_DIR="${ALERT_STATE_DIR:-${ALERT_DIR}/.alert-state}"
ALERT_CONF="${ALERT_CONF:-/root/.dentfluence-alerts.conf}"
ALERT_REPEAT_HOURS="${ALERT_REPEAT_HOURS:-6}"
ALERT_HOSTNAME="$(hostname)"

mkdir -p "$ALERT_DIR" "$ALERT_STATE_DIR" 2>/dev/null
# shellcheck disable=SC1090
[ -f "$ALERT_CONF" ] && . "$ALERT_CONF"

_alert_log () {
  printf '%s  %-4s  %-22s  %s\n' "$(date -u '+%Y-%m-%d %H:%M:%SZ')" "$1" "$2" "$3" >> "$ALERT_LOG"
}

# --- channels. Each returns non-zero on failure; none of them may throw. ------

_send_telegram () { # _send_telegram <text>
  [ -n "${ALERT_TELEGRAM_TOKEN:-}" ] && [ -n "${ALERT_TELEGRAM_CHAT:-}" ] || return 9
  curl -sS -m 15 -o /dev/null -w '%{http_code}' \
    "https://api.telegram.org/bot${ALERT_TELEGRAM_TOKEN}/sendMessage" \
    --data-urlencode "chat_id=${ALERT_TELEGRAM_CHAT}" \
    --data-urlencode "text=$1" | grep -q '^200$'
}

_send_brevo_email () { # _send_brevo_email <subject> <text>
  [ -n "${ALERT_BREVO_KEY:-}" ] && [ -n "${ALERT_EMAIL_TO:-}" ] || return 9
  local code
  code=$(curl -sS -m 20 -o /dev/null -w '%{http_code}' -X POST \
    https://api.brevo.com/v3/smtp/email \
    -H "api-key: ${ALERT_BREVO_KEY}" \
    -H 'Content-Type: application/json' \
    -d "$(jq -n --arg to "$ALERT_EMAIL_TO" --arg s "$1" --arg t "$2" \
          --arg from "${ALERT_EMAIL_FROM:-no-reply@dentfluence.in}" \
          '{sender:{email:$from,name:"Dentfluence Ops"},
            to:[{email:$to}], subject:$s, textContent:$t}')")
  [ "$code" = "201" ] || [ "$code" = "202" ]
}

_send_webhook () { # _send_webhook <severity> <key> <text>
  [ -n "${ALERT_WEBHOOK_URL:-}" ] || return 9
  curl -sS -m 15 -o /dev/null -w '%{http_code}' -X POST "$ALERT_WEBHOOK_URL" \
    -H 'Content-Type: application/json' \
    -d "$(jq -n --arg sev "$1" --arg key "$2" --arg text "$3" --arg host "$ALERT_HOSTNAME" \
          '{severity:$sev,check:$key,text:$text,host:$host}')" \
    | grep -qE '^2[0-9][0-9]$'
}

_dispatch () { # _dispatch <severity> <key> <text>
  local sev="$1" key="$2" text="$3" sent=0 subject
  subject="[Dentfluence ${sev}] ${key}"
  _send_telegram    "${subject}"$'\n'"${text}" && sent=1
  _send_brevo_email "${subject}" "${text}"     && sent=1
  _send_webhook     "$sev" "$key" "$text"      && sent=1
  if [ "$sent" -eq 0 ]; then
    _alert_log "SELF" "no_channel" "nothing is configured to receive alerts - see docs/ALERTING.md"
    return 1
  fi
  return 0
}

# --- the public function -----------------------------------------------------

notify () { # notify <CRIT|WARN|OK> <key> <message>
  local sev="$1" key="$2" msg="$3"
  local sf="${ALERT_STATE_DIR}/${key}"
  local prev="" prev_at=0 now
  now=$(date +%s)
  if [ -f "$sf" ]; then prev=$(cut -d' ' -f1 < "$sf"); prev_at=$(cut -d' ' -f2 < "$sf"); fi

  _alert_log "$sev" "$key" "$msg"

  local should_send=0
  if [ "$sev" = "OK" ]; then
    # only announce a recovery, and only if it was previously bad
    [ -n "$prev" ] && [ "$prev" != "OK" ] && should_send=1
  else
    if [ "$prev" != "$sev" ]; then
      should_send=1
    elif [ $(( (now - prev_at) / 3600 )) -ge "$ALERT_REPEAT_HOURS" ]; then
      should_send=1
    fi
  fi

  if [ "$should_send" -eq 1 ]; then
    local text
    if [ "$sev" = "OK" ]; then text="RECOVERED: ${msg}"; else text="${msg}"; fi
    _dispatch "$sev" "$key" "${text}"$'\n'"host ${ALERT_HOSTNAME}, $(date -u '+%Y-%m-%d %H:%M UTC')"
    echo "$sev $now" > "$sf"
  else
    # keep the severity, keep the original time so the repeat clock is honest
    echo "$sev ${prev_at:-$now}" > "$sf"
  fi
}
