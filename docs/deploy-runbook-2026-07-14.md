# Deploy Runbook — Production Hardening (2026-07-14)

**Deploy when the clinic is CLOSED.** This commit changes payment recording,
wallet debits, appointment booking and the audit chain. None of it has been
executed against real data.

---

## STEP 0 — Local: push

```bash
cd /d "E:\Dentfluence\Dentfluence_OS\Dentfluence Web"
git push origin main
```

---

## STEP 1 — VPS: BACKUP FIRST (do not skip)

```bash
cd /var/www/dentfluence          # adjust if your path differs
bash backup.sh
ls -lh backups/ | tail -3        # confirm a fresh dump exists
```

**If backup.sh fails, STOP. Do not continue.**

---

## STEP 2 — VPS: set .env keys BEFORE new code runs

Generate the audit key:

```bash
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

Then edit `.env` and add / set:

```
AUDIT_HASH_KEY=<paste the 64-char hex from above>
SESSION_SECURE_COOKIE=true
SESSION_ENCRYPT=true
```

> Set `AUDIT_HASH_KEY` **before** any new audit rows are written. If you add it
> later, every row written in between will fail verification and look like
> tampering.

---

## STEP 3 — VPS: deploy

```bash
php artisan down --render="errors::503"

git pull origin main
composer install --no-dev --optimize-autoloader

php artisan migrate               # additive indexes only — no data changes

php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear
```

---

## STEP 4 — VPS: re-anchor the audit chain (one time)

Production has the **same** JSON-canonicalisation bug, so its chain will fail.
Confirm the cause before rebuilding — do not skip straight to `--backfill`.

```bash
php artisan audit:verify          # EXPECTED TO FAIL on prod
php artisan audit:diagnose        # must say: "CONTENT does not hash to stored value"
```

**If `audit:diagnose` says anything OTHER than a content mismatch — STOP and tell
Claude.** A chain *gap* (deleted rows) or a *rebuild* means something else
happened and must not be papered over.

If it confirms the content mismatch (the known bug):

```bash
php artisan audit:verify --backfill
php artisan audit:verify          # must now be OK, legacy rows = 0
```

---

## STEP 5 — VPS: bring it back up + verify

```bash
php artisan up

php artisan config:cache
php artisan route:cache

php artisan app:crawl-routes --url="https://srv1791841.hstgr.cloud"
```

Expect **0 broken**.

---

## STEP 6 — VPS: confirm the automation backbone is actually running

The whole recall/reminder/automation layer silently depends on cron + a queue
worker. If either isn't running, everything stops with **no error surfaced**.

```bash
# Cron — must contain a schedule:run line
crontab -l | grep schedule:run

# Queue worker — must show a running process
ps aux | grep "[q]ueue:work"

# Scheduled jobs registered
php artisan schedule:list
```

If cron is missing:

```bash
crontab -e
# add:
* * * * * cd /var/www/dentfluence && php artisan schedule:run >> /dev/null 2>&1
```

If no queue worker (supervisor is better, but to test):

```bash
php artisan queue:work --daemon &
```

---

## STEP 7 — VPS: smoke test the money paths (5 min, do this before staff arrive)

1. Record a payment → **double-click Save** → confirm exactly ONE receipt.
2. Apply a coupon on the web invoice form → confirm the server-computed amount.
3. Drag an appointment onto an occupied slot → confirm the "book anyway?" prompt.
4. Revise a plan with a completed/invoiced tooth → confirm that item survives.
5. Register a patient with an existing phone → confirm the duplicate prompt.

---

## ROLLBACK (if anything is wrong)

```bash
php artisan down

git log --oneline -3              # find the commit BEFORE this deploy
git reset --hard <previous-sha>
composer install --no-dev --optimize-autoloader

php artisan config:clear && php artisan cache:clear && php artisan view:clear
php artisan up
```

The migration only **adds indexes** — it is safe to leave in place after a code
rollback. Nothing depends on them existing.

**The audit re-anchor (Step 4) cannot be rolled back.** That is fine and
expected: it rebuilt hashes that were already unverifiable.

If the database itself needs restoring:

```bash
gunzip < backups/db_<timestamp>.sql.gz | mysql -u <user> -p <database>
```

---

## Known behaviour changes to tell staff about

- **Duplicate phone at registration** now prompts ("open existing / register
  anyway") instead of silently creating a second patient. Families sharing a
  number is fine — they just confirm.
- **Double-booking a doctor** now asks for confirmation before it will save.
- **Recall list will grow** on the first run: patients with no phone number were
  previously invisible to recall forever. They now appear flagged
  "⚠ No contact number on file".
