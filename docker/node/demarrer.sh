#!/bin/sh
# Démarrage du front Nuxt, optimisé pour Docker Desktop sous Windows (partage de dossier lent) :
#  1. dépendances réinstallées seulement si package-lock.json a changé ;
#  2. build relancé seulement si les sources ont changé depuis le dernier démarrage ;
#  3. le serveur tourne depuis /srv/front (volume Docker, rapide), pas depuis le dossier partagé.
set -e
cd /app
CIBLE=/srv/front

EMPREINTE_DEPS=$(md5sum package-lock.json | cut -d' ' -f1)
if [ "$(cat node_modules/.empreinte 2>/dev/null)" != "$EMPREINTE_DEPS" ]; then
  echo "[front] Installation des dépendances (package-lock.json modifié)…"
  npm ci --no-audit --no-fund
  echo "$EMPREINTE_DEPS" > node_modules/.empreinte
fi

# Empreinte des sources : chemin, taille et date de modification (rapide, sans lire les fichiers)
EMPREINTE_SOURCES=$(find app public nuxt.config.ts package.json package-lock.json tsconfig.json -type f -exec stat -c '%n %s %Y' {} + 2>/dev/null | sort | md5sum | cut -d' ' -f1)
if [ "$(cat "$CIBLE/.empreinte" 2>/dev/null)" != "$EMPREINTE_SOURCES" ] || [ ! -f "$CIBLE/server/index.mjs" ]; then
  echo "[front] Compilation (sources modifiées)…"
  npm run build
  rm -rf "$CIBLE"/* "$CIBLE"/.empreinte 2>/dev/null || true
  cp -r .output/. "$CIBLE"/
  echo "$EMPREINTE_SOURCES" > "$CIBLE/.empreinte"
else
  echo "[front] Sources inchangées : compilation précédente réutilisée."
fi

exec node "$CIBLE/server/index.mjs"
