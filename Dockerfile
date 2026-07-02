# =============================================================================
# Dentfluence — Production Dockerfile (PHP 8.3 FPM)
# Multi-stage build:
#   Stage 1 (frontend)  -> compiles Vite/Tailwind assets with Node
#   Stage 2 (app)       -> PHP-FPM image with all extensions + composer deps
# The result is ONE image that contains the full app, ready to run as the
# php-fpm process. nginx (separate container) talks to it on port 9000.
# =============================================================================

# -----------------------------------------------------------------------------
# Stage 1: Build front-end assets (CSS/JS) with Node + Vite
# -----------------------------------------------------------------------------
FROM node:20-alpine AS frontend

WORKDIR /app

# Install JS deps first (better layer caching — only re-runs when lockfile changes)
COPY package.json package-lock.json ./
RUN npm ci

# Copy the files Vite needs to build, then build the production assets
COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
RUN npm run build
# Output lands in public/build (referenced by Laravel's @vite directive)


# -----------------------------------------------------------------------------
# Stage 2: PHP-FPM application image
# -----------------------------------------------------------------------------
FROM php:8.3-fpm-bookworm AS app

# --- System libraries required by the PHP extensions we enable below ----------
RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libzip-dev \
        libicu-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libwebp-dev \
        libonig-dev \
        libmagickwand-dev \
        default-mysql-client \
    && rm -rf /var/lib/apt/lists/*

# --- PHP core extensions ------------------------------------------------------
RUN docker-php-ext-configure gd --with-jpeg --with-freetype --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        intl \
        opcache

# --- Imagick (used by some image/scan features) -------------------------------
RUN pecl install imagick \
    && docker-php-ext-enable imagick

# --- Composer (copied from the official composer image) -----------------------
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# --- Install PHP dependencies (production only — no dev packages) -------------
# Copy only composer files first so this layer is cached unless deps change.
COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-interaction \
        --prefer-dist \
        --no-scripts \
        --optimize-autoloader

# --- Copy the rest of the application ----------------------------------------
COPY . .

# --- Bring in the compiled front-end assets from Stage 1 ----------------------
COPY --from=frontend /app/public/build ./public/build

# --- Finish composer setup (runs post-install scripts now that app is present)-
RUN composer dump-autoload --optimize --no-dev

# --- Permissions: web server must own storage + bootstrap/cache --------------
RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# --- Production PHP config + entrypoint (provided in Chunk 2) ------------------
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-dentfluence.ini
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

EXPOSE 9000

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]
