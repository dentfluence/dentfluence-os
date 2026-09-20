# Dentfluence — Alerting

**Built and proven 19 September 2026. Tracker row 1.5.**

Before this, nothing on the box told anyone when something broke. `failed_jobs`
went unwatched, nothing noticed if the scheduler loop died, and on 7 September a
0-byte database dump was written and nobody saw it for days. The machinery below
fixes that.

**One thing is still missing: a channel.** Everything is wired and tested, but
`MAIL_USERNAME` in `.env.production` is still the placeholder
`your-brevo-smtp-login@example.com`, and no Telegram, webhook or WhatsApp
credential exists. Until a channel is switched on, alerts are written to
`backups/alerts.log` and nothing reaches a phone. **Switching one on is a
five-minute job and it is the last step of row 1.5.**

---

## What watches what

`ops/healthcheck.sh` runs **hourly** from root's crontab and checks:

| Check | Fires when |
|---|---|
| `containers` | any of the 6 containers is not running |
| `mysql` | the mysql container is not `healthy` |
| `site` | `https://…/login` does not return 200 |
| `tls` | certificate expires in under 14 days (CRIT under 7) |
| `failed_jobs` | anything is sitting in the failed queue |
| `scheduler` | the scheduler has not logged for over 20 minutes |
| `backup_local` | the newest local dump is over 26 hours old |
| `backup_offsite` | the newest off-site dump is over 26 hours old |
| `disk` | root filesystem over 85% (CRIT over 92%) |
| `restarts_*` | a container has restarted 5 or more times |

`backup.sh` reports through the same notifier: a verification failure, an
off-site mismatch, a missing remote, or the script dying part-way through — an
`EXIT` trap catches a full disk, a dead mysql or a Ctrl+C, so a half-finished
backup cannot pass for a good one.

## How it behaves

`notify <CRIT|WARN|OK> <key> <message>` in `ops/alerts.sh` is the only way
anything raises an alarm. It:

- **always** appends to `backups/alerts.log`, whatever else fails;
- sends only when the state **changes**, then at most once every
  `ALERT_REPEAT_HOURS` (default 6) while it is still bad — an hourly check on a
  broken thing does not mean 24 messages a day;
- sends a recovery **once**, prefixed `RECOVERED:`;
- never takes the caller down. A dead channel is logged, not fatal.

State lives in `backups/.alert-state/<key>`. Delete a file there to force the
next alert to be re-sent.

---

## Switching a channel on

Credentials go in `/root/.dentfluence-alerts.conf`, `chmod 600`, **not in git**.
Configure any or all; every configured channel gets every alert.

### Telegram — recommended, free, about two minutes

The fastest way to get alerts onto a phone, with no account, card or domain.

1. In Telegram, message **@BotFather**, send `/newbot`, give it a name. It
   replies with a token like `8123456789:AAH…`.
2. Create a group (e.g. "Dentfluence Ops"), add the bot to it.
3. Send one message in the group, then open
   `https://api.telegram.org/bot<TOKEN>/getUpdates` in a browser and read the
   `chat.id` — for a group it is negative, e.g. `-1002345678901`.
4. On the VPS:

       cat > /root/.dentfluence-alerts.conf <<'CONF'
       ALERT_TELEGRAM_TOKEN="8123456789:AAH…"
       ALERT_TELEGRAM_CHAT="-1002345678901"
       CONF
       chmod 600 /root/.dentfluence-alerts.conf

5. Test:

       cd /opt/dentfluence && . ./ops/alerts.sh && \
         notify CRIT channeltest "if this reaches the phone, alerting is live" && \
         rm -f backups/.alert-state/channeltest

### Email through Brevo's HTTP API

Uses the API, not SMTP, so the broken `MAIL_*` block in `.env.production` is
irrelevant. Get an API key from Brevo → SMTP & API → API Keys.

    ALERT_BREVO_KEY="xkeysib-…"
    ALERT_EMAIL_TO="sumitfirke1@gmail.com"
    ALERT_EMAIL_FROM="no-reply@dentfluence.in"

Email is slower to notice than Telegram and lands in a crowded inbox. Good as a
second channel, weak as the only one.

### Generic webhook

Anything that accepts a JSON POST — n8n, Zapier, a WhatsApp gateway, the
Dentfluence app itself later.

    ALERT_WEBHOOK_URL="https://…"

Payload: `{"severity","check","text","host"}`.

---

## Proven on 19 September 2026

- All ten checks ran green on production: 6 containers, mysql healthy, login
  200, TLS 71 days, 0 failed jobs, scheduler 0 min, backups 9 h, disk 17%.
- A CRIT was raised three times in a row and dispatched **once** — repeats
  suppressed. The recovery was sent once.
- With a webhook configured, delivery succeeded (no `no_channel` line).
- `gotenberg` was stopped: `CRIT containers — container(s) not running:
  gotenberg`. Started again: `OK containers — all 6 containers running`.
- `backup.sh` was made to die part-way: `CRIT backup — backup aborted (exit 7)`.

## Known gaps, stated not hidden

- **No channel is configured yet.** Alerts are logged only. This is the one
  remaining step.
- **Nothing watches the watcher.** If the VPS is off, or cron itself is dead, no
  alert can be sent from it — by definition. An external uptime ping
  (healthchecks.io, UptimeRobot) would close that hole and is worth adding
  before clinic #2.
- The hourly cadence means up to an hour of blindness. Fine for backups and
  disk; a customer-facing outage would be noticed by staff sooner anyway.
