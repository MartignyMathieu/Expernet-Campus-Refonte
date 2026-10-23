#!/bin/sh
# Démarrage de l'API en production : configuration depuis l'environnement, migrations, données de référence, caches.
set -e
cd /var/www/backend

[ -n "$APP_KEY" ] || { echo "APP_KEY manquante : générez-la avec « docker compose -f docker-compose.prod.yml run --rm backend php artisan key:generate --show »."; exit 1; }

# php-fpm ne transmet pas l'environnement aux workers : on écrit backend/.env depuis les variables du conteneur.
printenv | grep -E '^(APP_|DB_|SESSION_|SANCTUM_|MAIL_|MFA_|EMARGEMENT_|ASSIDUITE_|SEED_|LOG_|CACHE_|QUEUE_)' \
  | sed 's/^\([^=]*\)=\(.*\)$/\1="\2"/' > .env

php artisan migrate --force
# Données de référence uniquement (rôles, catalogue, contenus) : pas de comptes de démonstration (SEED_DEMO=false).
php artisan db:seed --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

chown -R www-data:www-data storage bootstrap/cache
exec "$@"
