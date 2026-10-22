# 1. Analyse de l'existant

> Sources consultées le 22/10/2026 : site public expernet-campus.re, page de connexion GALIA, capture de l'accueil stagiaire GALIA fournie par l'équipe, retour d'usage de Microsoft Teams par l'équipe.
> Convention : ✅ observé · ⚠️ déduit · ❓ à confirmer.

## 1.1 Site public (WordPress)

| Constat | Statut |
|---|---|
| WordPress + Elementor + WP Rocket + plugin Instagram Feed | ✅ |
| 8 titres RNCP (5 informatique, 3 marketing digital) + déclinaisons Mayotte | ✅ |
| Charte : bleu `#009BDE`, bleu foncé `#0D5A8A`, jaune `#FFC329`, corail `#F87C56`, police Roboto | ✅ (variables Elementor) |
| Recherche plein texte générique, **aucun filtre** filière / niveau / CPF | ✅ |
| Fiches : objectifs, prérequis, métiers, code RNCP, rythme ; **aucune mention du financement CPF** | ✅ |
| Pas de formulaire sur les fiches : renvoi vers des pages Inscription / Contact | ✅ |
| Incohérences : doublon d'URL TSRS, texte « 2 cursus marketing » pour 3, section Partenaires vide, bandeau cookies affichant `{title}` | ✅ |
| Accessibilité : lien d'évitement présent ; images sans texte alternatif ou dupliqué | ✅ |
| Aucun lien vers l'ENT (GALIA) ni Teams | ✅ |

## 1.2 GALIA (SC-Form, portail stagiaire)

| Constat | Statut |
|---|---|
| Portail SC-Form « GALIA 10.50.1.9 », connexion identifiant / mot de passe, pas de MFA visible | ✅ |
| Technologie ASP.NET WebForms ; **identifiant de session placé dans l'URL** (`(S(...))`) : risque de fuite par copier-coller ou en-tête Referer | ⚠️ |
| Menu stagiaire : Accueil, GED, Planning, Abonnement planning, Parcours, Travail à faire, Notes, Évolution, Évaluation, Questionnaire de satisfaction, Livret d'apprentissage | ✅ (capture) |
| Accueil : planning du jour (grille horaire), parcours de la semaine (cours + présence en entreprise), bilan présence / retards / absences, dernières notes avec coefficient, travail à faire, messages | ✅ (capture) |
| Interface dense, petits caractères, codes couleur non expliqués ; planning et parcours affichent deux fois la même information | ✅ (capture) |
| Aucune fonction d'émargement côté stagiaire visible ; l'assiduité est seulement consultée | ✅ (capture) |
| API d'intégration disponible | ❓ à demander à l'éditeur SC-Form |
| Vue formateur (saisie des notes, feuilles de présence) | ❓ capture formateur à obtenir |

## 1.3 Microsoft Teams

Usage réel déclaré par l'équipe : **échanges avec le formateur et entre étudiants**, et **partage de documents**.

| Décision | Justification |
|---|---|
| Conserver Teams pour la messagerie et la visio | Outil maîtrisé et gratuit dans Microsoft 365 Éducation ; le refaire n'apporte rien au barème |
| Intégrer le **partage de documents pédagogiques** dans l'ENT | Les supports sont rattachés à la promotion et au module, avec des droits par rôle |
| Évolution possible : connexion unique Microsoft (Entra ID) | Hors périmètre du prototype ; ❓ à confirmer : tous les apprenants ont-ils un compte M365 ? |

## 1.4 Synthèse comparative

| Critère | Site WordPress | GALIA | Teams |
|---|---|---|---|
| Rôle | Vitrine, acquisition | Scolarité (référentiel) | Collaboration |
| Utilisateurs | Visiteurs, candidats | Étudiants, formateurs, administration | Tous |
| Points forts | SEO de base, Qualiopi visible | Données officielles complètes | Usage quotidien, temps réel |
| Limites | Pas de facettes, pas de CPF, formulaires dispersés | Interface datée, session dans l'URL, pas de MFA, pas d'émargement | Documents non structurés par module |
| Décision | **Remplacé** par le catalogue intégré | **Fonctions clés reprises** dans l'ENT | **Conservé** (chat, visio) ; documents intégrés à l'ENT |

## 1.5 Besoins par profil

- **Visiteur** : trouver une formation en 3 clics, connaître le niveau et le financement, candidater.
- **Étudiant** : un seul espace pour le planning, l'émargement, les notes et les documents.
- **Formateur** : faire signer rapidement, repérer l'absentéisme, déposer ses supports, évaluer en jury.
- **Administrateur** : gérer les comptes et les rôles, planifier les examens, composer les jurys, suivre les candidatures.
