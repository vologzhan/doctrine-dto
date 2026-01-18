FROM php:7.4-fpm-alpine

RUN apk add --no-cache \
    git \
    unzip \
    zip \
    curl

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app