# Types de connexion à la plateforme

| Profil | Compte de démonstration | Étapes de connexion | Arrivée |
|---|---|---|---|
| **Visiteur** | aucun | Aucune : site public (catalogue, formation continue, blog, FAQ, candidature, contact) | `/` |
| **Apprenant** | `etudiant@expernet.test` (et `lucas@`, `ines@`… `@expernet.test`) | E-mail + mot de passe | `/ent/etudiant` |
| **Formateur** | `formateur@expernet.test`, `formatrice@expernet.test` | E-mail + mot de passe + **code TOTP à 6 chiffres** (obligatoire) | `/ent/formateur` |
| **Administration** | `admin@expernet.test` | E-mail + mot de passe + **code TOTP à 6 chiffres** (obligatoire) | `/ent/admin` |

Mot de passe des comptes de démonstration : valeur de `DEMO_PASSWORD` dans le fichier `.env`.
Les rôles qui exigent la double authentification se règlent avec `MFA_OBLIGATOIRE_POUR` (par défaut `admin,formateur`).

## Les parcours

1. **Connexion simple (apprenant)** : e-mail et mot de passe, puis redirection vers son espace. Un apprenant peut activer la double authentification de lui-même (*Mon profil*, section *Sécurité du compte*) ; elle lui sera alors demandée à chaque connexion.
2. **Première connexion d'un formateur ou d'un administrateur** : après le mot de passe, l'espace reste bloqué (`MFA_REQUIRED`) et la page *Mon profil* s'ouvre sur la section *Sécurité du compte* :
   1. *Activer la double authentification* ;
   2. scanner le QR code avec Google Authenticator, Microsoft Authenticator ou toute application TOTP (ou saisir la clé affichée) ;
   3. saisir le code à 6 chiffres pour confirmer ;
   4. **noter les 8 codes de secours**, affichés une seule fois.
3. **Connexion suivante (formateur, administration)** : mot de passe, puis code à 6 chiffres de l'application. Un même code ne sert qu'une fois : juste après l'activation, attendre le code suivant (30 secondes).
4. **Téléphone indisponible** : *Utiliser un code de secours* sur l'écran du code ; chaque code de secours ne sert qu'une fois.
5. **Changer de téléphone, ou laisser quelqu'un l'activer sur le sien** (démonstration) : chacun peut, depuis *Mon profil*, section *Sécurité du compte* (menu à son nom), cliquer sur **Désactiver ma double authentification** et confirmer avec son mot de passe. Pour un formateur ou un administrateur, l'espace reste bloqué jusqu'à la réactivation avec un nouveau QR code.
6. **Téléphone perdu ou application réinstallée** : l'administration clique sur *Réinitialiser* (colonne MFA de *Utilisateurs & rôles*). À la connexion suivante, la personne réactive sa double authentification (parcours 2).
7. **Administrateur bloqué** (ou plus aucun administrateur disponible) : en ligne de commande, sur le serveur :
   ```bash
   docker compose exec backend php artisan mfa:reinitialiser admin@expernet.test
   ```

## Changer de compte sur le même navigateur

Une seule session est ouverte à la fois par navigateur. Si quelqu'un est déjà connecté, la page de connexion affiche **« Déjà connecté »** avec son nom, et propose :
- *Accéder à mon espace* ;
- *Se connecter avec un autre compte*, qui ferme la session en cours avant d'afficher le formulaire.

Pour tester plusieurs profils en même temps (formateur et apprenant côte à côte), utiliser une fenêtre de navigation privée ou un second navigateur.

## Protections

- Session sécurisée (cookie `HttpOnly`, chiffré, `SameSite=Lax`) et jeton CSRF sur chaque requête qui modifie des données.
- 5 tentatives de connexion par minute et par adresse, 5 codes MFA par minute : au-delà, réponse 429.
- Compte désactivé par l'administration : connexion refusée et sessions en cours coupées.
- Le secret TOTP et les codes de secours sont **chiffrés avec `APP_KEY`** (`backend/.env`).

## Point d'attention : la clé `APP_KEY`

Si le projet est relancé depuis un autre dossier sans son `backend/.env`, une nouvelle clé est générée et les secrets MFA existants deviennent illisibles. L'écran de connexion affiche alors : *« Votre double authentification ne peut plus être vérifiée… »*. Deux solutions :

- recopier l'ancienne ligne `APP_KEY=` dans `backend/.env`, puis `docker compose restart backend` (rien à réactiver) ;
- ou réinitialiser la MFA des comptes concernés (parcours 6 ou 7), qui la réactivent ensuite.
