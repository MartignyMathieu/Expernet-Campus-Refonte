# Image de production de l'API : code et dépendances figés dans l'image (pas de dossier partagé),
# dépendances de développement exclues, OPcache sans vérification des fichiers.
FROM composer:2 AS dependances
WORKDIR /app
COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-autoloader

FROM php:8.4-fpm-alpine
RUN apk add --no-cache libzip-dev libpng-dev freetype-dev postgresql-dev icu-dev \
 && docker-php-ext-install pdo_pgsql zip gd intl opcache
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/prod/php.ini /usr/local/etc/php/conf.d/zz-production.ini

WORKDIR /var/www/backend
COPY backend/ ./
COPY --from=dependances /app/vendor ./vendor
RUN rm -f .env && composer dump-autoload --no-dev --optimize --classmap-authoritative \
 && chown -R www-data:www-data storage bootstrap/cache

COPY docker/prod/php-entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh
ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
