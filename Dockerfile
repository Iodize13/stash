# Production image: FrankenPHP serves public/ directly (no separate Nginx).
# The same image runs the web server, the queue worker and the scheduler;
# Kubernetes picks which one with the container command.

ARG PHP_IMAGE=dunglas/frankenphp:1-php8.4-bookworm

# --- PHP dependencies ---------------------------------------------------------
FROM ${PHP_IMAGE} AS base
RUN install-php-extensions pdo_pgsql intl zip bcmath pcntl opcache
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app

FROM base AS vendor
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist

# --- Frontend assets ------------------------------------------------------------
FROM oven/bun:1 AS assets
WORKDIR /app
COPY package.json bun.lock ./
RUN bun install --frozen-lockfile
COPY . .
# Tailwind scans some vendor views (pagination), so they must exist at build time.
COPY --from=vendor /app/vendor ./vendor
RUN bun run build

# --- Runtime ---------------------------------------------------------------------
FROM base AS app
ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    SERVER_NAME=:8080 \
    XDG_CONFIG_HOME=/home/stash/.config \
    XDG_DATA_HOME=/home/stash/.local/share

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && printf 'opcache.enable=1\nopcache.validate_timestamps=0\nmemory_limit=256M\nexpose_php=Off\n' > "$PHP_INI_DIR/conf.d/zz-stash.ini"

COPY --from=vendor /app/vendor ./vendor
COPY . .
COPY --from=assets /app/public/build ./public/build

# Autoloader + package discovery + Filament's published JS/CSS (gitignored in the repo).
RUN composer dump-autoload --optimize --no-dev --no-interaction \
    && php artisan package:discover --ansi \
    && php artisan filament:assets \
    && chmod +x docker/entrypoint.sh

# Run as an unprivileged user; port 8080 needs no extra capability.
RUN useradd --uid 10001 --create-home stash \
    && setcap -r /usr/local/bin/frankenphp \
    && mkdir -p /home/stash/.config /home/stash/.local/share \
    && chown -R stash:stash /home/stash /app/storage /app/bootstrap/cache
USER 10001

EXPOSE 8080
ENTRYPOINT ["docker/entrypoint.sh"]
CMD ["frankenphp", "php-server", "--listen", ":8080", "--root", "public/"]
