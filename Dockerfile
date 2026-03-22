FROM kaz29/php-apache:8.4.1

# COPY config/apache/default.conf /etc/apache2/sites-available/000-default.conf
# COPY config/apache/apc.ini /usr/local/etc/php/conf.d/

WORKDIR /srv/app
