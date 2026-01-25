FROM php:7.4-fpm-alpine

RUN apk add --no-cache \
    git \
    unzip \
    zip \
    curl \
    postgresql-dev \
    $PHPIZE_DEPS

RUN docker-php-ext-install pdo pdo_pgsql pgsql

RUN pecl install xdebug-3.1.6 \
    && docker-php-ext-enable xdebug

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
