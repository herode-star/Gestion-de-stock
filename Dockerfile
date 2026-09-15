FROM php:7.4-apache

RUN docker-php-ext-install pdo_mysql mysqli

COPY docker/apache-directory-index.conf /etc/apache2/conf-available/directory-index.conf
RUN a2enconf directory-index

WORKDIR /var/www/html
COPY . .
