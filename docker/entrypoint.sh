#!/bin/sh
set -e
# Listen on the port the platform provides (default 80).
PORT="${PORT:-80}"
sed -i "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:[0-9]*>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf
mkdir -p storage/sessions storage/logs storage/uploads
chown -R www-data:www-data storage
# Create the tables on first start only (never touches an existing database).
if [ "${AUTO_INSTALL_SCHEMA:-1}" = "1" ] && [ "${DB_DRIVER:-mysql}" = "mysql" ]; then
  php tools/install_schema.php || echo "WARNING: schema install failed; check DB_* variables" >&2
fi
exec "$@"
