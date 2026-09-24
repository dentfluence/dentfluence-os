#!/usr/bin/env bash
# =============================================================================
# Dentfluence — Deploy script (run ON the VPS, inside the project folder)
# -----------------------------------------------------------------------------
# Does a full, safe deploy in one command:
#   1. Pulls latest code (if this is a git checkout)
#   2. Rebuilds the Docker images
#   3. Starts/updates all containers
#   4. Runs database migrations (--force = non-interactive, safe for prod)
#   5. Caches config/routes/views for speed
#   6. Restarts the queue worker so it picks up new code
#
# Usage:   ./deploy.sh
# First-time setup is covered in DEPLOY.md (Chunk 5).
# =============================================================================
set -euo pipefail

ENV_FILE=".env.production"
COMPOSE="docker compose --env-file ${ENV_FILE}"

echo "==> Dentfluence deploy starting ($(date '+%Y-%m-%d %H:%M:%S'))"

# --- 0. Safety checks --------------------------------------------------------
if [ ! -f "${ENV_FILE}" ]; then
  echo "ERROR: ${ENV_FILE} not found. Copy it into this folder and fill in the values first."
  exit 1
fi

# --- 1. Get latest code (skip silently if not a git repo) --------------------
if [ -d .git ]; then
  echo "==> Pulling latest code..."
  git pull --ff-only || echo "   (git pull skipped/failed — continuing with current code)"
fi

# --- 2. Build images ---------------------------------------------------------
echo "==> Building Docker images..."
# --no-cache is deliberate. The Dockerfile bakes the code in with `COPY . .`,
# it is not a bind mount, so a cached layer can leave the container serving the
# OLD code while `git log` on the host shows the new commit. That has already
# bitten this box once (9 Jul 2026), diagnosed only because a brand-new
# migration did not even appear as Pending.
# nginx is built too: its config is baked into its own image, so a change to
# docker/nginx/default.conf never went live through this script (found 24 Sep
# 2026 while deploying SEC-01). Building while the old containers still serve
# costs no downtime.
${COMPOSE} build --no-cache app
${COMPOSE} build nginx

# --- 3. Stop the old code, THEN back up, THEN migrate, THEN start (L-10) -------
# Until 24 Sep 2026 the new containers started first, so new code ran against
# the old schema and the dump was taken from a system already changing.
# Downtime is now the dump + migrate time (usually under a minute).
echo "==> Stopping app, queue and scheduler..."
${COMPOSE} stop app queue scheduler

# --- 3a. Back up BEFORE touching the schema -----------------------------------
# The deploy stops dead if the dump is missing, too small, or not valid gzip.
echo "==> Taking a pre-migration backup..."
PRE_STAMP="$(date '+%Y-%m-%d_%H-%M-%S')"
PRE_DUMP="backups/pre_deploy_${PRE_STAMP}.sql.gz"
mkdir -p backups
set -a; . "./${ENV_FILE}"; set +a
${COMPOSE} exec -T mysql \
  mysqldump -u root -p"${DB_ROOT_PASSWORD}" \
  --single-transaction --quick --routines --triggers \
  "${DB_DATABASE}" | gzip > "${PRE_DUMP}"

PRE_SIZE=$(stat -c%s "${PRE_DUMP}" 2>/dev/null || echo 0)
if [ "${PRE_SIZE}" -lt 1000000 ] || ! gzip -t "${PRE_DUMP}" 2>/dev/null; then
  echo "!! DEPLOY ABORTED: the pre-migration dump is only ${PRE_SIZE} bytes or is corrupt." >&2
  echo "   ${PRE_DUMP}" >&2
  echo "   No migration has run. Restarting the OLD containers so the clinic keeps working." >&2
  ${COMPOSE} start app queue scheduler
  exit 1
fi
echo "    pre-migration dump OK: ${PRE_DUMP} (${PRE_SIZE} bytes)"

# --- 3b. Migrate with the NEW image, before any new container serves ----------
echo "==> Running database migrations (new code, nothing serving yet)..."
if ! ${COMPOSE} run --rm --no-deps app php artisan migrate --force; then
  echo "!! MIGRATION FAILED. The app is stopped; the old containers still exist." >&2
  echo "   Old code back up:   ${COMPOSE} start app queue scheduler" >&2
  echo "   Schema restore:     gunzip < ${PRE_DUMP} | ${COMPOSE} exec -T mysql mysql -u root -p\"\${DB_ROOT_PASSWORD}\" ${DB_DATABASE}" >&2
  exit 1
fi

# --- 3c. Start the new containers --------------------------------------------
# --force-recreate: a plain `up -d` can silently no-op if compose decides
# nothing changed, and then the new image is never used.
echo "==> Starting containers on the new code..."
${COMPOSE} up -d --force-recreate app queue scheduler nginx
${COMPOSE} up -d
echo "==> Waiting for app container to be ready..."
sleep 5

# --- 5. Cache config/views (production speed) --------------------------------
# NOTE: `route:cache` is intentionally NOT run. A few routes use redirect
# *action closures* (e.g. web.php `/`, `/crm`, module `/settings` redirects),
# and route:cache aborts on any closure route — which would fail the deploy
# after migrations already ran. Routes resolve per-request instead (negligible
# overhead). To re-enable route:cache later, convert those `fn() => redirect()`
# routes to `Route::redirect(...)` (cacheable) first.
echo "==> Optimizing (config/view cache)..."
${COMPOSE} exec -T app php artisan config:cache
${COMPOSE} exec -T app php artisan view:cache
${COMPOSE} exec -T app php artisan storage:link --force || true

# --- 6. Warm the Today's Actions projection (Workstream E) -------------------
# So the reception dashboard / Huddle snapshot aren't empty until the scheduler
# first fires. Idempotent + shadow (reads only use it once `today.projection` is
# flipped on). Never fail the deploy if this hiccups.
echo "==> Warming Today's Actions projection..."
${COMPOSE} exec -T app php artisan today:rebuild-projection || true

# --- 7. Restart queue + scheduler so they run the new code -------------------
echo "==> Restarting queue & scheduler..."
${COMPOSE} restart queue scheduler

echo "==> Deploy complete. App is live behind nginx (container port 80 -> host 8080)."
echo "    Check status with:  ${COMPOSE} ps"
echo "    View logs with:     ${COMPOSE} logs -f app"
