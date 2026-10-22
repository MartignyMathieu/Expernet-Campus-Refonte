# Expernet Campus : plateforme unifiée (catalogue + ENT)

MARTIGNY Mathieu
LAW THIME Kenjy

Projet du titre **Concepteur Développeur d'Applications (CDA)**. Refonte du système d'information d'Expernet Campus : le site WordPress, le portail GALIA et le partage de documents Teams sont réunis en **une seule plateforme API-first**.

| | |
|---|---|
| Équipe | **MartignyMathieu** · **chinesekaii** |
| Dépôt | https://github.com/MartignyMathieu/Expernet-Campus-Refonte |
| Rendu | 23/10/2026 |
| Stack | Laravel 13 · Nuxt 4 · PostgreSQL 16 · Docker Compose |

## Fonctionnalités

**Module public** (rendu serveur, SEO)
- Catalogue des 8 titres RNCP réels d'Expernet, recherche à facettes : filière, niveau RNCP, financement CPF
- Fiches détaillées : objectifs, prérequis, modalités d'accès, débouchés ; données structurées schema.org
- Formulaires de candidature et de contact avec validation côté client et côté serveur, consentement RGPD, pot de miel anti-robots, limitation de débit
- **Contenus repris de l'ancien site** : FAQ (9 questions), blog (12 derniers articles, images d'origine), offre de formation continue reprise intégralement de expernet.re (149 formations, 14 domaines) : catalogue filtrable, fiches détaillées (objectifs, programme, tarifs, prochaines sessions)
- **Migration du blog** : commande `php artisan blog:import-wordpress https://expernet-campus.re` (API REST WordPress, HTML assaini contre les injections)
- Accessibilité (RGAA) : lien d'évitement, focus visible, libellés, erreurs reliées aux champs, contrastes AA, navigation au clavier, carrousel avec pause
- Animations sobres (apparition au défilement, compteurs, transitions de page), désactivées si le système demande de réduire les animations
- **Responsive vérifié automatiquement** sur 25 pages × 8 largeurs d'écran (320 à 1440 px)

**Module ENT**
- Authentification par session sécurisée + **double authentification TOTP réelle**, obligatoire pour les formateurs et les administrateurs, avec codes de secours
- **RBAC** : rôles admin, formateur, étudiant ; 13 permissions ; cloisonnement des données par promotion
- **Émargement numérique** par demi-journée : code à 6 chiffres, signature au doigt, horodatage, empreinte SHA-256 vérifiable, clôture automatique des absents, **attestation PDF**
- **Tableau de bord formateur** (navigation à motifs de bananes ) : promotions suivies, taux d'assiduité, alertes d'absentéisme
- **Examens** : planification des sessions, composition des jurys (un président), saisie des grilles de compétences
- **Planning** en vue agenda (semaine, mois, liste) avec export `.ics` vers Outlook ou Google Agenda ; notes avec moyenne pondérée, espace documentaire par promotion avec **aperçu en ligne** (PDF, images, textes)
- **Messagerie** : conversations privées et groupes ; l'étudiant écrit à sa promotion, à ses formateurs et à l'administration ; le formateur crée des groupes (choix des membres ou toute une promotion) et les gère ; pièces jointes (5 fichiers de 10 Mo) et émoticônes ; modification (24 h) et suppression de ses messages, modération par le gestionnaire du groupe ; compteur de non-lus
- Administration : comptes, rôles, activation, candidatures, indicateurs

## Prérequis

- **Docker Desktop** (Windows : avec WSL 2 activé)
- Git
- Ports libres : 8080 (application) et 8025 (Mailpit)

## Installation et lancement

Depuis un terminal **PowerShell**, dans le dossier où cloner le projet :

```powershell
git clone https://github.com/MartignyMathieu/Expernet-Campus-Refonte.git
cd Expernet-Campus-Refonte
Copy-Item .env.example .env
notepad .env        # renseigner DB_PASSWORD et DEMO_PASSWORD
docker compose up -d --build
```

Le premier démarrage installe les dépendances, génère la clé, applique les migrations et charge le catalogue et les données de démonstration (environ 3 à 5 minutes). Suivi : `docker compose logs -f backend frontend`.

| URL | Contenu |
|---|---|
| http://localhost:8080 | Site public + ENT |
| http://localhost:8080/api/docs | Documentation interactive de l'API (Swagger UI) |
| http://localhost:8080/openapi.json | Spécification OpenAPI 3.0 |
| http://localhost:8025 | Boîte mail de développement (Mailpit) |

**Comptes de démonstration** (personnes fictives, mot de passe = `DEMO_PASSWORD`) : `admin@expernet.test`, `formateur@expernet.test`, `formatrice@expernet.test`, `etudiant@expernet.test`. À la première connexion d'un formateur ou d'un administrateur, l'activation de la MFA est demandée : scanner le QR code avec Google Authenticator ou Microsoft Authenticator. Tous les parcours (codes de secours, réinitialisation) : [docs/07](docs/07-connexions.md).

## Tests

```powershell
docker compose exec backend vendor/bin/phpunit
```

81 tests et 354 assertions (unitaires et intégration), dont 16 sur l'émargement et 11 sur la messagerie. Ils passent sur SQLite (par défaut) et sur PostgreSQL :

```powershell
docker compose exec -e DB_CONNECTION=pgsql -e DB_HOST=db backend vendor/bin/phpunit
```

Intégration continue : `.github/workflows/ci.yml` exécute les tests (SQLite + PostgreSQL) et le build du front à chaque push et Pull Request.

> Attention : sur PostgreSQL, les tests utilisent la base configurée et la **réinitialisent**. Utilisez une base dédiée aux tests.

## Développement sans Docker (optionnel)

```powershell
# Terminal 1 : API (PHP 8.3+, Composer)
cd backend
composer install
Copy-Item .env.example .env ; notepad .env   # renseigner DEMO_PASSWORD
php artisan key:generate
New-Item database\database.sqlite -ItemType File
php artisan migrate --seed
php artisan serve            # http://localhost:8000

# Terminal 2 : front (Node 22)
cd frontend
npm install
npm run dev                  # http://localhost:3000 (relaie /api vers :8000)
```

## Structure

```
├── 67.php                  fichier vide exigé par le cahier des charges (non exposé par Nginx)
├── docker-compose.yml      postgres · backend (php-fpm) · frontend (Nuxt) · nginx · mailpit
├── docker/                 Dockerfile PHP, script de démarrage, configuration Nginx
├── backend/                API Laravel
│   ├── app/Http/Controllers/Api/   contrôleurs REST
│   ├── app/Http/Middleware/        RBAC (permission:) et MFA obligatoire
│   ├── app/Services/               EmargementService, AssiduiteService
│   ├── database/migrations/        schéma 3NF versionné
│   ├── database/seeders/           matrice RBAC, catalogue réel, démo
│   ├── public/openapi.json         contrat d'API
│   └── tests/                      Feature + Unit
├── frontend/               Nuxt 4
│   └── app/{pages,components,layouts,composables,middleware}
└── docs/                   analyse, architecture, MCD/MLD, organisation, charte, scénario de démo
```

## Documentation

1. [Analyse de l'existant](docs/01-analyse-existant.md)
2. [Architecture technique et sécurité](docs/02-architecture.md)
3. [Modèle de données (MCD / MLD, 3NF)](docs/03-modele-donnees.md)
4. [Organisation du binôme et workflow Git](docs/04-organisation-binome.md)
5. [Charte graphique](docs/05-charte-graphique.md)
6. [Scénario de démonstration](docs/06-scenario-demo.md)
7. [Types de connexion et double authentification](docs/07-connexions.md)

## Hypothèses à valider avec Expernet

- **Éligibilité CPF** : non publiée sur le site actuel ; tous les titres RNCP sont marqués éligibles par défaut (modifiable).
- **GALIA** : aucune API connue. L'intégration envisagée est un import CSV (évolution).
- **Teams** : conservé pour la messagerie et la visio. Connexion unique Microsoft (Entra ID) possible en évolution.
- **Signature** : signature électronique *simple* (eIDAS). Une signature qualifiée nécessiterait un prestataire certifié.

## Crédits

Icônes : [Phosphor Icons](https://phosphoricons.com) (licence MIT). Logo, photographies, visuels des formations et du blog : © Expernet Campus, repris de [expernet-campus.re](https://expernet-campus.re) pour ce projet pédagogique. Les comptes de démonstration sont des personnes fictives.

## Conventions

Commits au format [Conventional Commits](https://www.conventionalcommits.org/fr/) (`feat(attendance): …`), branches `feat/*` à partir de `develop`, Pull Request relue avant fusion. Détails dans [docs/04](docs/04-organisation-binome.md).
