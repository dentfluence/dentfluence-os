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

# --- verify: a backup nobody checked is not a backup --------------------------
# On 7 Sep 2026 this script produced a 0-byte dump and nothing noticed. Every
# artifact is now checked for size AND gzip integrity, and the script exits
# non-zero if either fails, so cron mail and the alert in row 1.5 can see it.
verify_gz () {  # verify_gz <path> <min-bytes> <label>
  local f="$1" min="$2" label="$3" size
  if [ ! -f "$f" ]; then
    echo "!! BACKUP FAILED: ${label} was never written (${f})" >&2
    return 1
  fi
  size=$(stat -c%s "$f")
  if [ "$size" -lt "$min" ]; then
    echo "!! BACKUP FAILED: ${label} is only ${size} bytes (minimum ${min}) — ${f}" >&2
    return 1
  fi
  if ! gzip -t "$f" 2>/dev/null; then
    echo "!! BACKUP FAILED: ${label} is corrupt, gzip -t rejected it — ${f}" >&2
    return 1
  fi
  echo "    verified ${label}: ${size} bytes, gzip OK"
  return 0
}

RC=0
verify_gz "${DB_FILE}"    1000000 "database dump" || RC=1
verify_gz "${FILES_FILE}"  100000 "uploaded files" || RC=1
if [ "$RC" -ne 0 ]; then
  echo "==> BACKUP FAILED (${STAMP}). Old backups were NOT pruned." >&2
  exit 1
fi

# --- 3. Prune old backups ----------------------------------------------------
echo "==> Removing backups older than ${KEEP_DAYS} days..."
find "${BACKUP_DIR}" -name 'db_*.sql.gz'  -mtime +${KEEP_DAYS} -delete
find "${BACKUP_DIR}" -name 'files_*.tar.gz' -mtime +${KEEP_DAYS} -delete

# --- 4. Off-site copy, encrypted ---------------------------------------------
# Backups that only live on the VPS do not survive losing the VPS. Everything
# here goes through the rclone "offsite" remote, which is a crypt wrapper: the
# file is encrypted on this box before it leaves, so the cloud provider stores
# ciphertext under meaningless names and cannot read patient data.
# The crypt keys live in /root/.config/rclone/rclone.conf. WITHOUT THEM THESE
# COPIES CANNOT BE DECRYPTED — keep a copy somewhere that is not this server.
if rclone listremotes 2>/dev/null | grep -q '^offsite:'; then
  echo "==> Copying off-site (encrypted)..."
  rclone copy "${DB_FILE}"    offsite:daily/ --stats-one-line
  rclone copy "${FILES_FILE}" offsite:daily/ --stats-one-line

  # Confirm the bytes actually landed, rather than trusting the exit code.
  for f in "${DB_FILE}" "${FILES_FILE}"; do
    base="$(basename "$f")"
    local_size=$(stat -c%s "$f")
    remote_size=$(rclone size "offsite:daily/${base}" --json 2>/dev/null | sed 's/.*"bytes":\([0-9]*\).*/\1/')
    if [ "$remote_size" != "$local_size" ]; then
      echo "!! OFF-SITE FAILED: ${base} is ${local_size} bytes here but ${remote_size:-missing} off-site" >&2
      exit 1
    fi
    echo "    off-site verified ${base}: ${remote_size} bytes"
  done

  # On the 1st, keep a monthly copy as well.
  if [ "$(date '+%d')" = "01" ]; then
    echo "==> Monthly retention copy..."
    rclone copy "${DB_FILE}"    offsite:monthly/ --stats-one-line
    rclone copy "${FILES_FILE}" offsite:monthly/ --stats-one-line
  fi

  echo "==> Pruning off-site (30 daily, 13 months)..."
  rclone delete offsite:daily   --min-age 30d  2>/dev/null || true
  rclone delete offsite:monthly --min-age 400d 2>/dev/null || true
else
  echo "!! OFF-SITE SKIPPED: no 'offsite' rclone remote is configured." >&2
  echo "   The only copies of this backup are on the VPS this backup protects." >&2
  exit 1
fi

echo "==> Backup complete (${STAMP})."
echo "    Local copy in ${BACKUP_DIR}/ and an encrypted copy off-site."
