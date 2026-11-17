#!/bin/sh
set -e
# Listen on the port the platform provides (default 80).
PORT="${PORT:-3000}"
sed -i "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:[0-9]*>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf
mkdir -p storage/sessions storage/logs storage/uploads
chown -R www-data:www-data storage
# Create the tables on first start only (never touches an existing database).
if [ "${AUTO_INSTALL_SCHEMA:-1}" = "1" ]; then
  php tools/install_schema.php || echo "WARNING: schema install failed; check DB_* variables" >&2
fi
# One-time additive production upgrade. Set AUTO_UPGRADE_2026_11_17=1 for one deployment,
# verify the completion line in logs, then remove the variable or set it to 0.
if [ "${AUTO_UPGRADE_2026_11_17:-0}" = "1" ]; then
  php tools/upgrade_2026_11_17.php
fi
exec "$@"
