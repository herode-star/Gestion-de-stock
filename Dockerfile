FROM php:8.3-apache

RUN apt-get update && apt-get install -y --no-install-recommends libcurl4-openssl-dev \
    && docker-php-ext-install pdo_mysql mysqli curl \
    && rm -rf /var/lib/apt/lists/*

COPY docker/apache-directory-index.conf /etc/apache2/conf-available/directory-index.conf
COPY docker/uploads.ini /usr/local/etc/php/conf.d/uploads.ini
RUN a2enconf directory-index

WORKDIR /var/www/html
COPY . .
RUN chown -R www-data:www-data /var/www/html/produit \
    && chmod -R 775 /var/www/html/produit
