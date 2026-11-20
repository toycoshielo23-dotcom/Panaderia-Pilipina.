#!/bin/sh
set -eu

storage_root="${APP_STORAGE_PATH:-/var/data}"
mkdir -p "$storage_root/data" "$storage_root/uploads"

for source_file in /var/www/html/data/*.json; do
    [ -f "$source_file" ] || continue
    target_file="$storage_root/data/${source_file##*/}"
    if [ ! -e "$target_file" ]; then
        cp "$source_file" "$target_file"
    fi
done

if [ -d /var/www/html/uploads ]; then
    cp -an /var/www/html/uploads/. "$storage_root/uploads/"
fi

chown -R www-data:www-data "$storage_root/data" "$storage_root/uploads"

port="${PORT:-10000}"
case "$port" in
    ''|*[!0-9]*)
        echo "PORT must contain only digits." >&2
        exit 1
        ;;
esac

sed -i "s/^Listen 80$/Listen $port/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:$port>/" /etc/apache2/sites-available/000-default.conf

exec apache2-foreground
