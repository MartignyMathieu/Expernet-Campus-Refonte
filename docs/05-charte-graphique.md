# 5. Mini-charte graphique

Couleurs **relevées sur le logo officiel** (pixels du PNG) et dans les variables Elementor du site actuel, complétées pour l'accessibilité. Logo, photos et visuels des formations sont **repris du site expernet-campus.re** (dossier `frontend/public/images/`).

| Jeton | Valeur | Usage |
|---|---|---|
| `--primaire` | `#009BDD` | Bleu du logo : identité, focus des champs, niveau des formations informatique |
| `--orange` | `#ED8F00` | Orange du logo (« Marketing digital ») : bouton d'action principal, filière marketing |
| `--marine` | `#0B2E4A` | Barre haute, pied de page, voiles sur les photos |
| `--primaire-fonce` | `#0D5A8A` | Boutons secondaires, titres d'accent |
| `--primaire-texte` | `#00719F` | Liens (contraste AA sur blanc ; `#009BDE` ne l'atteint pas) |
| `--jaune` | `#FFC329` | **Thème banane de l'espace formateur**, motif « carrés » |
| `--corail` | `#F87C56` | Réserve (illustrations) |
| `--encre` / `--texte` | `#191E23` / `#2B3138` | Titres / texte courant |
| Succès / alerte | `#1E7D46` / `#B4232A` | États de validation, assiduité |

- **Typographie** : Montserrat 700/800 pour les titres (proche des lettres du logo), Roboto pour le texte (police du site), 16 px minimum, échelle fluide `clamp()`.
- **Espacements** : base 8 px (4, 8, 16, 24, 40, 64).
- **Boutons** : arrondis, hauteur minimale 46 px (cible tactile) ; principal orange avec texte encre (contraste 7:1), secondaire contour bleu foncé, danger rouge.
- **Photos** : toujours sous un voile dégradé marine → bleu (marine → orange pour le marketing) pour garantir la lisibilité du texte blanc.
- **Motif « carrés »** : repris du site officiel, utilisé en signature graphique (pied de page, bandeaux, ENT).
- **Carrousel d'accueil** : bouton pause, arrêt au survol/focus, désactivé si « mouvement réduit » (RGAA 13.8).
- **Cartes** : rayon 12 px, bordure `#E3E6EA`, ombre légère.
- **Formulaires** : libellé visible au-dessus du champ, erreur sous le champ (`aria-invalid` et `aria-describedby`), message global annoncé (`role="alert"`).
- **Tableaux** : en-têtes `scope`, `caption` pour les lecteurs d'écran, défilement horizontal sur mobile.
- **Focus** : contour orange de 3 px sur tous les éléments interactifs ; lien « Aller au contenu ».
- **Mouvement** : animations désactivées si `prefers-reduced-motion`.

## Contrainte « bananes »

La navigation du **tableau de bord formateur** utilise un jeu d'icônes SVG de bananes (`components/icons/IconBanane.vue`). Chaque banane porte une pastille métier (planning, assiduité, jury, documents), et l'entrée active est surlignée en jaune Expernet. Les icônes sont décoratives (`aria-hidden`) et le libellé texte reste toujours affiché : la contrainte est respectée sans pénalité RGAA. Le jaune banane est aussi le jaune de la marque, ce qui l'intègre à l'identité visuelle.
