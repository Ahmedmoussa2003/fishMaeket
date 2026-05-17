FROM php:8.2-apache

RUN docker-php-ext-install pgsql pdo pdo_pgsql

COPY . /var/www/html/

RUN a2enmod rewrite

EXPOSE 80
