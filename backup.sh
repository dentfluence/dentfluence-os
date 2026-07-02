#!/usr/bin/env bash
# =============================================================================
# Dentfluence — Backup script (run ON the VPS)
# -----------------------------------------------------------------------------
# Creates a timestamped backup of:
#   1. The MySQL database (full dump)
#   2. User-uploaded files (storage/app — patient images, scans, documents)
# Keeps the last 14 days of backups and deletes older ones.
#
# Usage:        ./backup.sh
# Automate it:  add to crontab so it runs daily (see DEPLOY.md).
#               0 2 * * *  cd /opt/dentfluence && ./backup.sh >> backups/backup.log 2>&1
#
# RESTORE: see the "Restore" section in DEPLOY.md — and test it once before
#          you trust it. A backup you have never restored is not a backup.
# =============================================================================
set -euo pipefail

ENV_FILE=".env.production"
COMPOSE="docker compose --env-file ${ENV_FILE}"
BACKUP_DIR="backups"
KEEP_DAYS=14
STAMP="$(date '+%Y-%m-%d_%H-%M-%S')"

# Load DB credentials from the env file
# shellcheck disable=SC1090
set -a; . "./${ENV_FILE}"; set +a

mkdir -p "${BACKUP_DIR}"

echo "==> Backup starting (${STAMP})"

# --- 1. Database dump --------------------------------------------------------
DB_FILE="${BACKUP_DIR}/db_${STAMP}.sql.gz"
echo "==> Dumping database '${DB_DATABASE}'..."
${COMPOSE} exec -T mysql \
  mysqldump -u root -p"${DB_ROOT_PASSWORD}" \
  --single-transaction --quick --routines --triggers \
  "${DB_DATABASE}" | gzip > "${DB_FILE}"
echo "    -> ${DB_FILE} ($(du -h "${DB_FILE}" | cut -f1))"

# --- 2. Uploaded files (storage/app) -----------------------------------------
FILES_FILE="${BACKUP_DIR}/files_${STAMP}.tar.gz"
echo "==> Archiving uploaded files (storage/app)..."
# storage lives in the named volume; copy it out of the app container
${COMPOSE} exec -T app tar czf - -C /var/www/html/storage app > "${FILES_FILE}"
echo "    -> ${FILES_FILE} ($(du -h "${FILES_FILE}" | cut -f1))"

# --- 3. Prune old backups ----------------------------------------------------
echo "==> Removing backups older than ${KEEP_DAYS} days..."
find "${BACKUP_DIR}" -name 'db_*.sql.gz'  -mtime +${KEEP_DAYS} -delete
find "${BACKUP_DIR}" -name 'files_*.tar.gz' -mtime +${KEEP_DAYS} -delete

echo "==> Backup complete (${STAMP})."
echo "    IMPORTANT: copy ${BACKUP_DIR}/ off this server too (e.g. to S3 / another"
echo "    machine). A backup that only lives on the same VPS won't survive a disk loss."
