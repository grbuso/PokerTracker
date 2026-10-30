FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .
RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs \
    && rm -f bootstrap/cache/*.php \
    && composer dump-autoload --optimize --no-dev --no-interaction

FROM php:8.4-cli-alpine AS assets

RUN apk add --no-cache nodejs npm

WORKDIR /app

COPY --from=vendor /app .

RUN npm ci
RUN php artisan wayfinder:generate --with-form
RUN npm run build

FROM php:8.4-fpm-alpine

RUN apk add --no-cache nginx supervisor sqlite sqlite-dev \
    && docker-php-ext-install pdo_sqlite bcmath opcache

WORKDIR /var/www/html

COPY --from=vendor /app .
COPY --from=assets /app/public/build ./public/build

COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY --chmod=755 docker/entrypoint.sh /entrypoint.sh

EXPOSE 80

ENTRYPOINT ["sh", "/entrypoint.sh"]
