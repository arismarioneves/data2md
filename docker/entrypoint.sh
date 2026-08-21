#!/bin/sh
set -eu

DATA_DIR="${APP_DATA_DIR:?APP_DATA_DIR is required}"

# ── 1. Volume layout ──────────────────────────────────────────────────────────
for sub in md db logs ratelimit tmp; do
    mkdir -p "$DATA_DIR/$sub"
done

chown -R www-data:www-data "$DATA_DIR"
chmod 750 "$DATA_DIR" "$DATA_DIR"/md "$DATA_DIR"/db "$DATA_DIR"/logs "$DATA_DIR"/ratelimit "$DATA_DIR"/tmp

# ── 2. Configuration — refuse to start if anything is wrong ───────────────────
# Runs before step 3's shell arithmetic on purpose: MAX_FILE_SIZE_MB and the
# other numeric keys are validated here first. If this ran after, a
# non-numeric value would hit `$((...))` under `set -eu` and abort with
# dash's raw "Illegal number" instead of the aggregated, friendly message
# Config::fromEnvironment() produces — silently breaking the D8 promise that
# an invalid config always logs the full list of problems.
runuser -u www-data -- php /var/www/html/bin/data2md config:check || {
    echo "[data2md] FATAL: refusing to start with invalid configuration" >&2
    exit 1
}

# ── 3. PHP directives that cannot be set at runtime ───────────────────────────
# upload_max_filesize and post_max_size are PHP_INI_PERDIR, so they must be
# written to a file. Deriving them here keeps a single source of truth: the
# environment variables, not a second copy in an ini.
#
# REQUEST_CEILING duplicates src/helpers.php's request_ceiling_seconds() in
# shell, because this runs before PHP does and cannot call into it. Keep the
# two formulas in sync by hand if either changes.
POST_MAX=$((MAX_FILE_SIZE_MB + 16))
REQUEST_CEILING=$((CONVERT_TIMEOUT_SECONDS + CONVERT_QUEUE_WAIT_SECONDS + 60))

cat > /usr/local/etc/php/conf.d/99-data2md.ini <<EOF
upload_max_filesize = ${MAX_FILE_SIZE_MB}M
post_max_size = ${POST_MAX}M
memory_limit = ${PHP_MEMORY_LIMIT_MB}M
max_execution_time = ${REQUEST_CEILING}
max_input_time = ${REQUEST_CEILING}
EOF

# Apache reads these from the environment via ${...} in the vhost.
APACHE_LIMIT_REQUEST_BODY=$((POST_MAX * 1024 * 1024))
APACHE_TIMEOUT=$REQUEST_CEILING
export APACHE_LIMIT_REQUEST_BODY APACHE_TIMEOUT

# ── 4. External tools ─────────────────────────────────────────────────────────
for binary in pdftotext nice ionice; do
    command -v "$binary" >/dev/null 2>&1 || {
        echo "[data2md] FATAL: required binary not found: $binary" >&2
        exit 1
    }
done

# ── 5. Schema ─────────────────────────────────────────────────────────────────
runuser -u www-data -- php /var/www/html/bin/data2md migrate

echo "[data2md] ready — data dir $DATA_DIR, ${CONVERT_CONCURRENCY} conversion slot(s)"

exec apache2-foreground
