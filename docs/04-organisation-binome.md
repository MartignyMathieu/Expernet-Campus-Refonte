# 4. Organisation du binôme et workflow Git

## 4.1 Répartition par tranches verticales

Chacun est responsable d'un domaine **de la base de données à l'écran**, ce qui évite qu'un membre attende l'autre.

| | **MartignyMathieu** : public & contenus | **chinesekaii** : ENT & sécurité |
|---|---|---|
| Périmètre | Catalogue, fiches, candidature, contact, SEO/RGAA, documents, notes | Authentification, MFA, RBAC, planning, émargement, assiduité, examens et jurys, administration |
| Fichiers | `CatalogueController`, `CandidatureController`, `NoteController`, `DocumentController`, `pages/index`, `pages/formations/*`, `pages/contact`, `ent/etudiant/notes|documents` | `EmargementService`, `AssiduiteService`, middlewares, `ExamenController`, `AdminController`, `pages/connexion`, `ent/securite`, `ent/formateur/*`, `ent/admin/*` |
| Tests | `CatalogueTest`, `CandidatureTest` | `EmargementTest`, `AuthMfaTest`, `RbacTest`, `AssiduiteEtExamensTest` |
| Dépend de l'autre | Rôles et permissions (pour le back-office) | Tables `formations` et `promotions` |
| Validé quand | Filtres fonctionnels, Lighthouse SEO et accessibilité ≥ 90, tests verts | Tests d'émargement verts, aucun accès entre rôles non autorisé |

**Partagé (décidé à deux)** : modèle de données et migrations, `openapi.json` (le contrat d'API), charte graphique (`main.css`), `docker-compose.yml`.

## 4.2 Branches

```
main      ← version démontrable, protégée (Pull Request obligatoire)
 └─ develop   ← intégration quotidienne
     ├─ feat/catalogue-facettes
     ├─ feat/emargement-signature
     └─ fix/...
```

Règles : une branche par fonctionnalité, Pull Request relue par l'autre membre, `git pull --rebase origin develop` chaque matin. On ne modifie jamais une migration déjà fusionnée : on en crée une nouvelle.

## 4.3 Conventional Commits

```
feat(attendance): add signature endpoint with SHA-256 proof
fix(catalogue): reject unknown RNCP level filter
test(attendance): cover expired signing window
docs(api): document jury composition endpoint
chore(docker): add mailpit service
```

Types : `feat`, `fix`, `test`, `docs`, `refactor`, `style`, `chore`, `ci`. Portée entre parenthèses : `catalogue`, `auth`, `attendance`, `exams`, `admin`, `front`, `docker`.

## 4.4 Pull Request

1. `git switch develop && git pull --rebase` puis `git switch -c feat/ma-fonctionnalite`
2. Commits atomiques, puis `git push -u origin feat/ma-fonctionnalite`
3. Sur GitHub : **Compare & pull request** vers `develop`, description (quoi, pourquoi, comment tester)
4. L'autre membre relit et teste (`vendor/bin/phpunit`) puis approuve ; fusion en **Squash and merge**
5. En fin de sprint : Pull Request `develop` vers `main`

## 4.5 Ne jamais pousser

Fichiers `.env` réels, `APP_KEY`, mots de passe, codes de secours MFA, dumps de base, données personnelles réelles (captures GALIA non floutées, listes d'étudiants), URL contenant un identifiant de session, dossiers `vendor/` et `node_modules/`. Le `.gitignore` les exclut déjà ; en cas d'erreur, il faut changer le secret, pas seulement supprimer le fichier.
