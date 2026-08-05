FROM php:8.2-apache

COPY apache.conf /etc/apache2/sites-available/000-default.conf

RUN docker-php-ext-install pdo pdo_mysql mysqli

COPY . /var/www/html/

EXPOSE 80