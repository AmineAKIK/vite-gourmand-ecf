#!/bin/sh
set -eu

PORT="${PORT:-8080}"
case "$PORT" in
  ''|*[!0-9]*)
    echo "Invalid PORT: $PORT" >&2
    exit 64
    ;;
esac

max_attempts="${MIGRATION_STARTUP_ATTEMPTS:-5}"
case "$max_attempts" in
  ''|*[!0-9]*|0)
    echo "Invalid MIGRATION_STARTUP_ATTEMPTS: $max_attempts" >&2
    exit 64
    ;;
esac

sed -ri "s/^Listen [0-9]+$/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

attempt=1
while :; do
  set +e
  php /var/www/html/bin/migrate.php
  migration_status=$?
  set -e

  if [ "$migration_status" -eq 0 ]; then
    break
  fi

  if [ "$migration_status" -ne 75 ]; then
    echo "Database migration failed permanently; refusing to start Apache." >&2
    exit "$migration_status"
  fi

  if [ "$attempt" -ge "$max_attempts" ]; then
    echo "Database was unavailable after ${attempt} migration attempt(s)." >&2
    exit 75
  fi

  echo "Database unavailable on migration attempt ${attempt}; retrying shortly." >&2
  attempt=$((attempt + 1))
  sleep 2
done

# Railway's runtime may materialize Apache module links differently from the
# image build. Reassert the only MPM supported by mod_php immediately before
# Apache starts, then fail closed if the effective runtime config is invalid.
rm -f /etc/apache2/mods-enabled/mpm_event.load /etc/apache2/mods-enabled/mpm_event.conf \
      /etc/apache2/mods-enabled/mpm_worker.load /etc/apache2/mods-enabled/mpm_worker.conf
a2enmod mpm_prefork >/dev/null
apache2ctl configtest

exec apache2-foreground
