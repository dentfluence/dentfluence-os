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
${COMPOSE} build

# --- 3. Start / update containers --------------------------------------------
echo "==> Starting containers..."
${COMPOSE} up -d

# --- 4. Wait for the app container, then run migrations ----------------------
echo "==> Waiting for app container to be ready..."
sleep 5
echo "==> Running database migrations..."
${COMPOSE} exec -T app php artisan migrate --force

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
