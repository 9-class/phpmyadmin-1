FROM php:apache

RUN apt-get update && \
    apt-get install -y \
    libxml2-dev \
    && docker-php-ext-install mysqli pdo_mysql xml

COPY php.ini /usr/local/etc/php/

EXPOSE 80
