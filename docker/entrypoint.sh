#!/bin/sh
set -eu

PORT="${PORT:-8080}"
case "$PORT" in
  ''|*[!0-9]*)
    echo "Invalid PORT: $PORT" >&2
    exit 64
    ;;
esac

sed -ri "s/^Listen [0-9]+$/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

attempt=1
max_attempts="${MIGRATION_STARTUP_ATTEMPTS:-5}"
while ! php /var/www/html/bin/migrate.php; do
  if [ "$attempt" -ge "$max_attempts" ]; then
    echo "Database migrations failed after ${attempt} attempt(s)." >&2
    exit 1
  fi

  echo "Migration attempt ${attempt} failed; retrying shortly." >&2
  attempt=$((attempt + 1))
  sleep 2
done

exec apache2-foreground
