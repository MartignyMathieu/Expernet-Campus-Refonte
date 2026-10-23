#!/bin/sh
# Sauvegarde automatique : base PostgreSQL (format personnalisé pg_dump) et fichiers déposés (documents, signatures,
# photos), toutes les SAUVEGARDE_INTERVALLE secondes (24 h par défaut). Les sauvegardes plus anciennes que
# SAUVEGARDE_JOURS jours (14 par défaut) sont supprimées. Restauration : docs/08-mise-en-production.md.
set -eu
DOSSIER=/sauvegardes
INTERVALLE=${SAUVEGARDE_INTERVALLE:-86400}
JOURS=${SAUVEGARDE_JOURS:-14}
mkdir -p "$DOSSIER"

sauvegarder() {
  horodatage=$(date +%Y%m%d-%H%M%S)
  echo "[sauvegarde] $horodatage : base de données…"
  PGPASSWORD="$DB_PASSWORD" pg_dump -h db -U "$DB_USERNAME" -d "$DB_DATABASE" -Fc -f "$DOSSIER/base-$horodatage.dump.tmp"
  mv "$DOSSIER/base-$horodatage.dump.tmp" "$DOSSIER/base-$horodatage.dump"
  echo "[sauvegarde] $horodatage : fichiers déposés…"
  tar -czf "$DOSSIER/fichiers-$horodatage.tar.gz" -C /fichiers .
  find "$DOSSIER" -type f \( -name 'base-*.dump' -o -name 'fichiers-*.tar.gz' \) -mtime +"$JOURS" -delete
  nb=$(ls "$DOSSIER" | wc -l)
  if [ "$nb" -gt 1 ]; then echo "[sauvegarde] terminée : $nb fichiers conservés."; else echo "[sauvegarde] terminée : $nb fichier conservé."; fi
}

until pg_isready -h db -U "$DB_USERNAME" -d "$DB_DATABASE" >/dev/null 2>&1; do sleep 5; done
while true; do
  sauvegarder || echo "[sauvegarde] ÉCHEC $(date +%Y%m%d-%H%M%S)"
  sleep "$INTERVALLE"
done
