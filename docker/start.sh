#!/bin/sh
# Container start script (used by the Dockerfile).
set -e

# Railway (and similar hosts) tell us which port to listen on.
PORT="${PORT:-80}"
sed -ri "s/^Listen [0-9]+/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# storage/ may be an empty persistent volume: create the folders the app needs.
mkdir -p /var/www/html/storage/uploads/items /var/www/html/storage/sessions /var/www/html/storage/logs
chown -R www-data:www-data /var/www/html/storage
chmod 700 /var/www/html/storage/sessions

exec apache2-foreground
