#!/bin/sh
set -e
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true

# The translation editor writes these bind-mounted dictionaries as www-data.
# Preserve the host user's ownership while allowing PHP to save both locales.
if [ "$(id -u)" -eq 0 ] && [ -d /var/www/html/lang ]; then
    chgrp www-data /var/www/html/lang
    chmod g+rwx /var/www/html/lang
    for dictionary in /var/www/html/lang/kh.json /var/www/html/lang/km.json; do
        if [ -f "$dictionary" ]; then
            chgrp www-data "$dictionary"
            chmod g+rw "$dictionary"
        fi
    done
fi

exec docker-php-entrypoint "$@"
