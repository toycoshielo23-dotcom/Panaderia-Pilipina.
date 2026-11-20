FROM php:8.2-apache

ENV APP_STORAGE_PATH=/var/data

COPY . /var/www/html/
COPY docker/render-entrypoint.sh /usr/local/bin/render-entrypoint
COPY docker/render-storage.conf /etc/apache2/conf-available/render-storage.conf

RUN chmod +x /usr/local/bin/render-entrypoint \
    && a2enconf render-storage

EXPOSE 10000

ENTRYPOINT ["/usr/local/bin/render-entrypoint"]
