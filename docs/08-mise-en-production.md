# 8. Tests, mise en production et exploitation

## 8.1 Les trois niveaux de tests

| Niveau | Outil | Où | Commande |
|---|---|---|---|
| API (unitaires et intégration) | PHPUnit | `backend/tests` | `docker compose exec backend vendor/bin/phpunit` |
| Front (utilitaires et composants) | Vitest + @nuxt/test-utils | `frontend/tests/unit` | `docker compose exec frontend npm run test:unit` |
| Parcours complets | Playwright (Chromium) | `frontend/e2e` | `docker compose --profile tests run --rm e2e` |

**Première installation de Vitest** (une seule fois : la commande met à jour `package.json` et `package-lock.json`, à commiter ensuite) :

```powershell
docker compose exec frontend npm install -D vitest @nuxt/test-utils @vue/test-utils happy-dom
```

Les tests Playwright ont leurs propres dépendances (`frontend/e2e/package.json`) et tournent dans un conteneur dédié, qui installe Chromium au premier lancement. Le parcours apprenant utilise le mot de passe des comptes de démonstration (`DEMO_PASSWORD`). Rapport en cas d'échec : `frontend/e2e/playwright-report/`.

L'intégration continue (`.github/workflows/ci.yml`) enchaîne à chaque push et Pull Request : tests de l'API sur SQLite et PostgreSQL, tests et build du front, puis démarrage de la plateforme complète avec Docker Compose et parcours Playwright.

## 8.2 Différences entre développement et production

| | Développement (`docker-compose.yml`) | Production (`docker-compose.prod.yml`) |
|---|---|---|
| Code | dossier partagé, modifiable à chaud | figé dans les images (build) |
| Dépendances PHP | avec outils de test | `--no-dev`, autoload optimisé |
| PHP | erreurs affichées | erreurs journalisées, OPcache figé |
| Laravel | caches désactivés | caches de configuration, routes, vues et évènements |
| Accès | HTTP sur le port 8080 | HTTPS obligatoire (redirection 80 → 443), HSTS |
| Cookies | non sécurisés | `Secure`, `HttpOnly`, chiffrés |
| Comptes de démonstration | créés | jamais créés (`SEED_DEMO=false`) |
| E-mails | Mailpit (boîte de test) | serveur SMTP réel |
| Sauvegardes | aucune | quotidiennes, conservées 14 jours |
| Redémarrage | manuel | automatique (`restart: unless-stopped`) |

## 8.3 Déploiement sur un serveur

Prérequis : un serveur Linux avec Docker, un nom de domaine pointant sur le serveur, les ports 80 et 443 ouverts.

```bash
git clone https://github.com/MartignyMathieu/Expernet-Campus-Refonte.git && cd Expernet-Campus-Refonte
cp .env.production.example .env.production
nano .env.production                      # APP_URL, DB_PASSWORD, SANCTUM_STATEFUL_DOMAINS, SMTP…
docker compose -f docker-compose.prod.yml --env-file .env.production build
docker compose -f docker-compose.prod.yml --env-file .env.production run --rm --no-deps backend php artisan key:generate --show
#   → recopier la clé affichée dans APP_KEY de .env.production
```

**Certificat HTTPS.** Placer `fullchain.pem` et `privkey.pem` dans `docker/prod/certs/`. Avec Let's Encrypt, avant le premier démarrage (le port 80 doit être libre) :

```bash
docker run --rm -p 80:80 -v "$PWD/docker/prod/letsencrypt:/etc/letsencrypt" certbot/certbot \
  certonly --standalone -d ent.exemple.re --agree-tos -m admin@exemple.re
cp -L docker/prod/letsencrypt/live/ent.exemple.re/fullchain.pem docker/prod/letsencrypt/live/ent.exemple.re/privkey.pem docker/prod/certs/
```

Pour une démonstration sans nom de domaine, un certificat auto-signé suffit (le navigateur affichera un avertissement) :

```bash
openssl req -x509 -nodes -days 365 -newkey rsa:2048 -subj "/CN=localhost" \
  -keyout docker/prod/certs/privkey.pem -out docker/prod/certs/fullchain.pem
```

**Démarrage**, puis création du premier compte administrateur (le mot de passe est demandé de façon masquée) :

```bash
docker compose -f docker-compose.prod.yml --env-file .env.production up -d
docker compose -f docker-compose.prod.yml --env-file .env.production exec backend php artisan expernet:creer-admin admin@exemple.re
```

À la première connexion, la double authentification est demandée à l'administrateur.

**Mise à jour** : `git pull`, puis `docker compose -f docker-compose.prod.yml --env-file .env.production up -d --build`. Les migrations s'appliquent automatiquement au démarrage du backend.

## 8.4 Page de santé et supervision

`GET /api/sante` renvoie l'état de la base de données, du stockage des fichiers et du cache, avec leur temps de réponse :

```json
{ "statut": "ok", "environnement": "production", "verifications": { "base_de_donnees": { "ok": true, "ms": 3 }, "stockage": { "ok": true, "ms": 1 }, "cache": { "ok": true, "ms": 2 } } }
```

Code HTTP 200 si tout va bien, **503** si un composant est en panne. Docker l'utilise comme sonde (`healthcheck` du service nginx : `docker compose ps` affiche `healthy`), et un outil externe (UptimeRobot, Uptime Kuma…) peut l'interroger toutes les minutes. La page n'expose aucune donnée personnelle ni secret.

## 8.5 Sauvegardes et restauration

Le service `sauvegarde` produit chaque jour dans `./sauvegardes/` :
- `base-AAAAMMJJ-HHMMSS.dump` : la base PostgreSQL complète (format `pg_dump -Fc`) ;
- `fichiers-AAAAMMJJ-HHMMSS.tar.gz` : les fichiers déposés (documents, rendus, justificatifs, signatures, photos).

Les sauvegardes de plus de 14 jours sont supprimées (`SAUVEGARDE_JOURS`). **Copier régulièrement `./sauvegardes/` hors du serveur** (autre machine, stockage objet) : une sauvegarde sur le même disque ne protège pas d'une panne du disque.

Sauvegarde immédiate : `docker compose -f docker-compose.prod.yml restart sauvegarde`.

**Restauration** (à tester au moins une fois avant d'en avoir besoin) :

```bash
F=docker-compose.prod.yml; E=.env.production
docker compose -f $F --env-file $E stop backend
docker compose -f $F --env-file $E exec -T db sh -c 'pg_restore -U "$POSTGRES_USER" -d "$POSTGRES_DB" --clean --if-exists' < sauvegardes/base-AAAAMMJJ-HHMMSS.dump
docker compose -f $F --env-file $E run --rm --no-deps -v "$PWD/sauvegardes:/s" --entrypoint sh backend \
  -c 'tar -xzf /s/fichiers-AAAAMMJJ-HHMMSS.tar.gz -C storage/app/private'
docker compose -f $F --env-file $E start backend
```

## 8.6 Journal d'audit

Les actions sensibles sont tracées dans la table `journal_audit` et consultables par l'administration (ENT → *Journal d'audit*) : saisie et correction de notes, publication de devoirs, justification d'absences et dépôt de justificatifs, suppression de documents, cahier de textes, ouverture et clôture de l'émargement, création et modification de comptes, réinitialisation de la double authentification. Chaque ligne indique l'auteur, la date, l'adresse IP et, si utile, les valeurs avant et après. L'application n'offre aucun moyen de modifier ou supprimer une ligne.

## 8.7 Liste de contrôle avant ouverture

- [ ] `APP_KEY` générée, `APP_DEBUG=false`, mots de passe longs et uniques dans `.env.production` (jamais commité)
- [ ] HTTPS actif avec un certificat valide ; renouvellement Let's Encrypt planifié (tous les 60 jours)
- [ ] Serveur SMTP configuré et testé (une notification de note doit arriver par e-mail)
- [ ] Premier compte administrateur créé, double authentification activée
- [ ] `GET /api/sante` renvoie 200 et `docker compose ps` affiche `healthy`
- [ ] Une sauvegarde présente dans `./sauvegardes/`, copiée hors du serveur, et une restauration testée
- [ ] Pare-feu : seuls les ports 80, 443 (et SSH) ouverts ; PostgreSQL non exposé
