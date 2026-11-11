# syntax=docker/dockerfile:1
############################
# base: PHP 8.3 FPM + extensions + Composer + FFmpeg
############################
FROM php:8.3-fpm-alpine AS base

ARG UID=1000
ARG GID=1000

RUN apk add --no-cache \
        bash git unzip ffmpeg icu-libs libzip libpng libjpeg-turbo libwebp freetype \
        mysql-client fcgi \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
        icu-dev libzip-dev libpng-dev libjpeg-turbo-dev libwebp-dev freetype-dev linux-headers \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql gd intl zip bcmath pcntl exif opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-app.ini

# run php-fpm as a user matching the host user so bind-mounted files stay writable
RUN addgroup -g ${GID} app && adduser -D -u ${UID} -G app app \
    && sed -i 's/^user = www-data/user = app/; s/^group = www-data/group = app/' /usr/local/etc/php-fpm.d/www.conf

WORKDIR /var/www/html
ENV COMPOSER_HOME=/tmp/composer

############################
# dev: Xdebug (off by default, enable with XDEBUG_MODE=debug)
############################
FROM base AS dev
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS linux-headers \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && apk del .build-deps
ENV XDEBUG_MODE=off
USER app

############################
# assets: build Vite bundle for production
############################
FROM node:22-alpine AS assets
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
RUN npm run build

############################
# prod: opcache tuned, code + built assets baked in
############################
FROM base AS prod
COPY docker/php/opcache-prod.ini /usr/local/etc/php/conf.d/98-opcache.ini
COPY --chown=app:app . /var/www/html
COPY --chown=app:app --from=assets /app/public/build /var/www/html/public/build
USER app
RUN composer install --no-dev --optimize-autoloader --no-interaction \
    && php artisan storage:link || true
