# syntax=docker/dockerfile:1

# --- Build aset frontend -------------------------------------------------
FROM node:20-alpine AS assets
WORKDIR /app
COPY package*.json ./
RUN npm ci --no-audit --no-fund
COPY vite.config.js ./
COPY resources ./resources
RUN npm run build

# --- Dependensi PHP (tanpa dev) ------------------------------------------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --optimize-autoloader

# --- Runtime: php-fpm + nginx + supervisor --------------------------------
FROM php:8.2-fpm-alpine AS app
RUN apk add --no-cache nginx supervisor icu-dev oniguruma-dev libzip-dev \
 && docker-php-ext-install pdo_mysql intl mbstring zip pcntl opcache bcmath
WORKDIR /var/www/html

COPY --from=vendor /app/vendor ./vendor
COPY . .
COPY --from=assets /app/public/build ./public/build
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-notifora.ini
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf
COPY docker/supervisor/ /etc/supervisor.d/

RUN composer dump-autoload --optimize 2>/dev/null || true \
 && mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache

ARG ROLE=web
ENV ROLE=${ROLE}
EXPOSE 8040
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh
ENTRYPOINT ["/entrypoint.sh"]
