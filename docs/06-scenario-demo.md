# 6. Scénario de démonstration (environ 8 minutes)

Prérequis : `docker compose up -d --build`, puis ouvrir http://localhost:8080. Une application d'authentification sur téléphone (Google ou Microsoft Authenticator).

| # | Écran | À montrer | Point du barème |
|---|---|---|---|
| 1 | Accueil | Recherche, raccourcis Bac+2 / Bac+5 / CPF, données réelles du catalogue | Catalogue |
| 2 | `/formations` | Facettes filière + niveau RNCP + CPF ; l'URL change (partageable, SEO) | Catalogue |
| 3 | Fiche CDA | Rubriques obligatoires ; envoyer le formulaire vide pour montrer la validation, puis une candidature valide | Formulaire, RGAA |
| 4 | `/api/docs` | Swagger : endpoints, schémas, permission requise affichée sur chaque route | API-first, OpenAPI |
| 5 | Connexion formateur | Redirection forcée vers la **MFA**, scan du QR code, confirmation, codes de secours | Sécurité |
| 6 | Tableau de bord formateur | **Navigation banane**, indicateurs, alertes d'absentéisme | Contrainte, tableau de bord |
| 7 | Séance du jour, puis **Émargement** | Ouvrir : le code s'affiche en grand | Émargement |
| 8 | Téléphone ou 2e navigateur : étudiant | Saisie du code, signature au doigt, preuve SHA-256 ; le compteur 1/8 se met à jour côté formateur | Émargement |
| 9 | Clôturer | Les non-signataires passent absents | Émargement |
| 10 | Étudiant, puis Documents | **Attestation PDF** avec empreintes | Attestations |
| 11 | Jurys & compétences | Saisie de la grille par un membre du jury | Examens |
| 12 | Terminal | `docker compose exec backend vendor/bin/phpunit` : 64 tests verts | Tests |
| 13 | GitHub | Branches, Pull Requests, Conventional Commits, `67.php` à la racine | Git, contraintes |

Comptes de démonstration (personnes fictives) : `admin@expernet.test`, `formateur@expernet.test`, `formatrice@expernet.test`, `etudiant@expernet.test`. Le mot de passe est celui défini dans `DEMO_PASSWORD`.
