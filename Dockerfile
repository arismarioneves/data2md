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
    pdftotext -v; \
    command -v ionice

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html

# ── Alvo de desenvolvimento e teste ───────────────────────────────────────────
FROM base AS dev
ENV APP_ENV=development
COPY composer.json ./
RUN composer install --no-interaction --no-scripts

# ── Alvo de produção ──────────────────────────────────────────────────────────
FROM base AS app
COPY composer.json ./
RUN composer install --no-interaction --no-dev --no-scripts
COPY src/ ./src/
COPY public/ ./public/
COPY bin/ ./bin/
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev
