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
COPY composer.json ./
RUN composer install --no-interaction --no-scripts

# ── Production ────────────────────────────────────────────────────────────────
FROM base AS app

COPY docker/php/php.ini          /usr/local/etc/php/conf.d/90-data2md.ini
COPY docker/apache/data2md.conf  /etc/apache2/sites-available/000-default.conf
COPY docker/apache/mpm.conf      /etc/apache2/conf-available/data2md-mpm.conf
RUN a2enconf data2md-mpm

COPY composer.json ./
RUN composer install --no-interaction --no-dev --no-scripts

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
    APP_NAME=data2md \
    APP_VERSION=1.1.0 \
    APP_DATA_DIR=/var/lib/data2md \
    MAX_FILE_SIZE_MB=200 \
    CONVERT_TIMEOUT_SECONDS=900 \
    CONVERT_CONCURRENCY=2 \
    CONVERT_QUEUE_WAIT_SECONDS=120 \
    CONVERT_NICE=15 \
    PHP_MEMORY_LIMIT_MB=512 \
    RATE_LIMIT_MAX=20 \
    RATE_LIMIT_WINDOW_SECONDS=60 \
    TRUSTED_PROXIES=10.11.0.0/16 \
    CORS_ALLOWED_ORIGINS= \
    LOG_RETENTION_DAYS=90 \
    AUDIT_LOG_RETENTION_DAYS=30 \
    GC_PROBABILITY=0.01 \
    CONTACT_EMAIL=contato@data2md.com.br

VOLUME ["/var/lib/data2md"]
EXPOSE 80

HEALTHCHECK --interval=30s --timeout=10s --start-period=15s --retries=3 \
    CMD php -r 'exit(@file_get_contents("http://127.0.0.1/health") && str_contains(@file_get_contents("http://127.0.0.1/health"), "\"status\":\"ok\"") ? 0 : 1);'

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
