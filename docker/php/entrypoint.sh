#!/bin/sh
# Prépare l'API au démarrage du conteneur : configuration, dépendances, clé, migrations, données.
set -e
cd /var/www/backend

# 1. backend/.env généré à partir des variables du conteneur (php-fpm ne transmet pas l'environnement aux workers).
ANCIENNE_CLE=$(grep '^APP_KEY=' .env 2>/dev/null | cut -d= -f2- | tr -d '"' || true)
printenv | grep -E '^(APP_|DB_|SESSION_|SANCTUM_|MAIL_|MFA_|EMARGEMENT_|ASSIDUITE_|SEED_|DEMO_)' | grep -v '^APP_KEY=' \
  | sed 's/^\([^=]*\)=\(.*\)$/\1="\2"/' > .env.tmp
CLE="${APP_KEY:-$ANCIENNE_CLE}"
echo "APP_KEY=\"$CLE\"" >> .env.tmp
mv .env.tmp .env

# 2. Dépendances PHP (volume Docker) : réinstallées seulement si composer.lock a changé
EMPREINTE=$(md5sum composer.lock | cut -d' ' -f1)
if [ ! -f vendor/autoload.php ] || [ "$(cat vendor/.empreinte 2>/dev/null)" != "$EMPREINTE" ]; then
  composer install --no-interaction --prefer-dist --optimize-autoloader
  echo "$EMPREINTE" > vendor/.empreinte
fi

# 3. Clé de chiffrement (une seule fois, conservée ensuite dans backend/.env)
if [ -z "$CLE" ]; then
  php artisan key:generate --force
  echo "ATTENTION : nouvelle APP_KEY générée. Si cette base contient déjà des comptes avec double authentification"
  echo "(projet déplacé dans un autre dossier), recopiez l'ancienne ligne APP_KEY dans backend/.env, ou réinitialisez :"
  echo "  docker compose exec backend php artisan mfa:reinitialiser <email>"
fi

# 4. Schéma et données
php artisan migrate --force
php artisan db:seed --force

# 5. La variable APP_KEY transmise par docker compose est vide : on exporte la clé réelle,
#    sinon php-fpm (clear_env = no dans l'image officielle) la masquerait.
export APP_KEY="$(grep '^APP_KEY=' .env | cut -d= -f2- | tr -d '"')"

chown -R www-data:www-data storage bootstrap/cache
exec "$@"
