FROM php:8.4-fpm-alpine

RUN apk add --no-cache git unzip libzip-dev libpng-dev freetype-dev postgresql-dev icu-dev \
 && docker-php-ext-install pdo_pgsql zip gd intl opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
