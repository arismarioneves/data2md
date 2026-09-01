# syntax=docker/dockerfile:1

FROM php:8.3-apache AS base

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        poppler-utils \
        util-linux \
        libonig-dev \
        libsqlite3-dev \
        unzip; \
    rm -rf /var/lib/apt/lists/*; \
    docker-php-ext-install -j"$(nproc)" mbstring pdo_sqlite opcache; \
    a2enmod rewrite headers; \
    a2dismod -f autoindex; \
    pdftotext -v; \
    command -v ionice

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html

# ── Development and test ──────────────────────────────────────────────────────
FROM base AS dev
ENV APP_ENV=development
COPY composer.json composer.lock ./
# --strict fails the build when composer.lock has drifted from composer.json.
RUN composer validate --no-check-publish --no-check-all --strict \
 && composer install --no-interaction --no-scripts --no-plugins

# ── Production ────────────────────────────────────────────────────────────────
FROM base AS app

# Stamped by CI: the semver tag on a release, or branch-shortsha on main.
ARG APP_VERSION=dev

COPY docker/php/php.ini          /usr/local/etc/php/conf.d/90-data2md.ini
COPY docker/apache/data2md.conf  /etc/apache2/sites-available/000-default.conf
COPY docker/apache/mpm.conf      /etc/apache2/conf-available/data2md-mpm.conf
RUN a2enconf data2md-mpm

COPY composer.json composer.lock ./
RUN composer validate --no-check-publish --no-check-all --strict \
 && composer install --no-interaction --no-dev --no-scripts --no-plugins --prefer-dist --no-progress

COPY src/    ./src/
COPY public/ ./public/
COPY bin/    ./bin/

RUN composer dump-autoload --optimize --classmap-authoritative --no-dev \
    && chmod +x bin/data2md

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Production defaults. APP_URL has none on purpose: there is no correct generic
# value, and deriving it from the request Host is the behaviour this project
# removed. Override any of these in the EasyPanel UI without rebuilding.
ENV APP_ENV=production \
    APP_NAME=Data2MD \
    APP_VERSION=${APP_VERSION} \
    APP_DATA_DIR=/var/lib/data2md \
    MAX_FILE_SIZE_MB=200 \
    CONVERT_TIMEOUT_SECONDS=900 \
    CONVERT_CONCURRENCY=2 \
    CONVERT_QUEUE_WAIT_SECONDS=20 \
    CONVERT_NICE=15 \
    PHP_MEMORY_LIMIT_MB=512 \
    RATE_LIMIT_MAX=20 \
    RATE_LIMIT_WINDOW_SECONDS=60 \
    TRUSTED_PROXIES=10.11.0.0/16 \
    CORS_ALLOWED_ORIGINS= \
    LOG_RETENTION_DAYS=90 \
    AUDIT_LOG_RETENTION_DAYS=30 \
    GC_PROBABILITY=0.01 \
    CONTACT_EMAIL=support@aiu4.com

VOLUME ["/var/lib/data2md"]
EXPOSE 80

# start-period=30s: covers boot (config:check, migrate, Apache warm-up) with
# margin over the ~9s observed. retries=5 at interval=30s gives ~150s of
# consecutive failures before a restart — a queued request now waits at most
# CONVERT_QUEUE_WAIT_SECONDS=20s before its worker frees up, so one busy burst
# costs at most one or two failed checks, never enough on its own to reach 5.
HEALTHCHECK --interval=30s --timeout=10s --start-period=30s --retries=3 \
    CMD php -r '$b = @file_get_contents("http://127.0.0.1/health"); exit(is_string($b) && str_contains($b, "\"status\":\"ok\"") ? 0 : 1);'

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
