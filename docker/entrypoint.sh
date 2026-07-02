#!/bin/sh
# =============================================================================
# Container entrypoint — runs every time the `app` container starts.
# Responsibilities (runtime prep only — NOT migrations, NOT config cache;
# those are handled by the deploy script so they run exactly once per deploy):
#   1. Make sure framework storage directories exist (named volume starts empty)
#   2. Fix ownership/permissions so PHP-FPM (www-data) can write
#   3. Create the public/storage symlink for web-accessible uploads
#   4. Copy compiled /public assets into the shared volume nginx serves from
# =============================================================================
set -e

cd /var/www/html

echo "[entrypoint] preparing storage directories..."
mkdir -p \
  storage/app/public \
  storage/framework/cache/data \
  storage/framework/sessions \
  storage/framework/views \
  storage/logs

echo "[entrypoint] fixing permissions..."
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

echo "[entrypoint] ensuring public/storage symlink..."
if [ ! -L public/storage ]; then
  php artisan storage:link --force 2>/dev/null || true
fi

# nginx serves static files from the shared `public_data` volume.
# Copy this image's freshly-built /public into it on every start so assets
# are never stale after a new deploy.
if [ -d /var/www/html/public_shared ]; then
  echo "[entrypoint] syncing public assets to shared volume..."
  cp -a /var/www/html/public/. /var/www/html/public_shared/ 2>/dev/null || true
fi

echo "[entrypoint] starting: $*"
exec "$@"
