# Dentfluence — Restore Runbook

**Drilled and passed: 19 September 2026, on a machine that is not the server.**
Tracker row 1.3.

This is what you do when the VPS is gone, corrupted, or wrong. It has been
rehearsed end to end, not just written down.

---

## What exists

Every night at **02:30 IST** (`0 21 * * *` UTC, root crontab on the VPS)
`/opt/dentfluence/backup.sh` runs and produces two files:

| File | Contents | Size today |
|---|---|---|
| `db_<stamp>.sql.gz` | full MySQL dump, 292 tables | ~5.2 MB |
| `files_<stamp>.tar.gz` | `storage/app` — X-rays, OPGs, photos, consent PDFs | ~30 MB |

Both are written to `/opt/dentfluence/backups/` **and** copied off-site,
**encrypted before they leave the server**, to Google Drive via the rclone
`offsite` crypt remote. Google stores ciphertext under meaningless names and
cannot read any of it. Retention: 30 daily, plus a monthly copy taken on the
1st and kept 13 months.

The script refuses to lie. Each artifact is checked for a minimum size and for
gzip integrity, and the off-site copy's size is read back and compared against
the local file. Any failure exits non-zero and **skips pruning**, so a broken
run can never delete the last good backup. Failures land in
`backups/BACKUP_FAILURES.log`.

## The one thing that must not be lost

The crypt keys. They live in two places:

- `/root/.config/rclone/rclone.conf` on the VPS
- `E:\Dentfluence\_keys\rclone-crypt-keys.txt` on Sumit's PC

**Without them the off-site copies cannot be decrypted by anyone, ever.** Keep a
third copy somewhere that is neither the VPS nor that PC.

---

## Restore procedure

Two scripts in `_release/` do this. They were used for the 19 Sep drill.

### 1. Database

    powershell -ExecutionPolicy Bypass -File .\RESTORE_DRILL.ps1

Lists the off-site backups, takes the newest dump, downloads and decrypts it,
decompresses it, and restores it into `dentfluence_restore_drill` — a throwaway
database. It never touches the real `dentfluence`.

### 2. The actual files

    powershell -ExecutionPolicy Bypass -File .\VERIFY_FILES.ps1

Downloads and decrypts the file archive, extracts it, and checks every clinical
file the restored database points at, byte size included. It then opens one
X-ray so a human can confirm with their own eyes that it renders.

### 3. Checking the restore is honest

    powershell -ExecutionPolicy Bypass -File .\VERIFY_DRILL.ps1

Row counts against production, then real records read back out.

**Update the expected counts in these scripts before trusting a red line.** In
the 19 Sep drill two counts "failed" only because the baseline had been measured
eight hours before the dump was taken; the backup was right and the baseline was
stale. A count that differs is a question, not a verdict.

### 4. Restoring for real, not as a drill

Same steps, then instead of the drill database:

    DROP DATABASE dentfluence;            -- only on a machine you mean to overwrite
    CREATE DATABASE dentfluence CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    mysql -u root dentfluence < db_<stamp>.sql

and unpack `files_<stamp>.tar.gz` into `storage/` so that `app/private/...` and
`app/public/...` land where they were.

**`APP_KEY` must be the one that encrypted the data.** The PHI columns are
encrypted with it; restore the database under a different `APP_KEY` and the
clinical fields are unreadable. It is in `.env.production`. Treat it as part of
the backup.

---

## Measured on 19 September 2026

Source: `db_2026-09-18_21-00-01.sql.gz` — the nightly cron dump, not a
hand-made one.

| Step | Time |
|---|---|
| Download + decrypt 5.1 MB dump | 7 s |
| Decompress to 33.4 MB SQL | instant |
| Restore into MySQL | 24.2 s |
| **Database usable** | **under 1 minute** |
| File archive download, extract, verify | ~1 minute |

Verified after restore: 292 tables; patients 3640, appointments 610,
invoices 261, invoice_items 343, treatment_visits 98, users 14,
clinical_files 4, treatment_plans 79 — all matching production measured the
same morning. Real records read back: patient TDC-5252, invoice
INV-2026-00261 with its line, treatment visit 109. All four clinical files
came back at the exact byte size, and the OPG was opened and rendered.

**So the honest answer to "how long to get the clinic running again" is: a few
minutes of restore, plus however long it takes to stand up a server.** The data
is not the bottleneck. That was not knowable before this drill.

---

## Known limits, stated not hidden

- **Google Drive is a 15 GiB account.** At ~35 MB/day the steady state is about
  1.5 GB, so there is room — but it is a ceiling. The plan is Backblaze B2 with
  a bucket and its own keys per clinic, provisioned by `tenants:create`, at
  Phase 4.
- **rclone's shared Google client_id is being retired during 2026.** Off-site
  copying will stop working when it goes. Create a private client_id before then
  (https://rclone.org/drive/#making-your-own-client-id).
- **The drill restores onto Laragon, not onto a fresh VPS.** Rebuilding the
  server itself — Docker, Caddy, `.env.production`, `APP_KEY` — is a separate
  runbook and has not been rehearsed since the 3 July rebuild.
