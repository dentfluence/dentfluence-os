# Dentfluence — Versioning & Zero-Data-Loss Updates

**Policy set 2026-07-03.** What is on the VPS now (after the first full push) is **V1 (git tag `v1.0`)**.

## Version scheme

| Version | Meaning | Example |
|---------|---------|---------|
| `v1.0` | Current go-live snapshot | everything built through Phase 2 Automation |
| `v1.1`, `v1.2`… | Normal updates: fixes, small features | billing chunk 2, recall trigger tidy-up |
| `v2.0` | Major update (only when we explicitly declare it) | ABDM layer, big schema overhauls |

Every release is a **git tag** on `main`. The VPS only ever runs a tagged version — never a random branch state.

## Why data is never lost

1. **MySQL lives in a Docker named volume** — rebuilding/updating app containers never touches it.
2. **`storage/` (patient files, uploads) is a mounted volume** — same protection.
3. **Migrations are additive-only on the VPS.** Never `migrate:fresh`, never `migrate:rollback` in production.
4. **`backup.sh` runs before every update** — full DB dump + storage archive, kept on the VPS and (recommended) downloaded to your PC.

## Releasing an update (V1 → V1.1)

On your PC:
```bash
git checkout main
git merge <feature-branch>        # or commit directly for small fixes
git tag v1.1
git push origin main --tags
```

On the VPS (SSH):
```bash
cd /path/to/dentfluence
./backup.sh                                      # ALWAYS first
git fetch --tags && git checkout v1.1
docker compose build app && docker compose up -d
docker compose exec app php artisan migrate --force
docker compose exec app php artisan config:clear && docker compose exec app php artisan cache:clear
```
Verify the site loads, then done. Total ~5 minutes.

## Rollback (if v1.1 breaks)

```bash
git checkout v1.0
docker compose build app && docker compose up -d
```
Code rolls back instantly. The database stays as-is (additive migrations don't break old code). Only restore the backup if a migration itself corrupted data — which additive-only migrations prevent.

## Major updates (V2)

Same procedure plus: test the full upgrade on local Laragon first, put the site in maintenance mode (`php artisan down`) during the update, download the backup off the VPS before starting, and `php artisan up` after verifying.

## One-time VPS setup for git-based updates

The VPS currently received code via upload. After the GitHub push, switch it to git once:
```bash
cd /path/to/dentfluence
git init -b main
git remote add origin https://github.com/dentfluence/dentfluence-os.git
git fetch --tags
git checkout -f v1.0        # aligns VPS files with the tag; .env and storage/ are untouched (gitignored)
```
From then on every update is the 5-minute procedure above.
