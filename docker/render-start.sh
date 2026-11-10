#!/bin/sh
# Start-up for the production container (Render):
#  1. listen on the port the host gives us ($PORT)
#  2. apply database migrations / first-time setup (safe to run every start)
#  3. start Apache
set -e

PORT="${PORT:-10000}"
sed -i "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:[0-9]*>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

for i in 1 2 3 4 5 6; do
  if php /var/www/sentra/bin/install.php; then
    break
  fi
  echo "Database not reachable yet (attempt $i), retrying in 5s..." >&2
  sleep 5
done

exec apache2-foreground
