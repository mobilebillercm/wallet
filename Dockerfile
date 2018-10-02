FROM php:7.2-apache

MAINTAINER ISERVICE "iservice@service.cm"

RUN docker-php-ext-install pdo_mysql

RUN docker-php-ext-install bcmath

RUN apt-get update && apt-get install -y --no-install-recommends apt-utils

RUN apt-get install supervisor -y

COPY ./supervisor/config/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

RUN a2enmod rewrite

COPY --chown=www-data:www-data . /var/www

COPY --chown=www-data:www-data ./public /var/www/html

EXPOSE 80

ENTRYPOINT ["/usr/bin/supervisord"]