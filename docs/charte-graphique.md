# Charte graphique — Vite & Gourmand

## Identité visuelle

L'identité visuelle de Vite & Gourmand évoque un traiteur bordelais familial, professionnel et qualitatif. Les choix graphiques sont sobres et chaleureux pour correspondre à une clientèle large et à l'ancrage local de l'entreprise (25 ans à Bordeaux).

---

## Palette de couleurs

| Usage | Nom | Hexadécimal |
|---|---|---|
| Couleur principale | Bordeaux | `#8B1A2B` |
| Variante sombre (hover) | Bordeaux foncé | `#6B1221` |
| Accent | Or | `#D4A843` |
| Accent clair | Or clair | `#E8C46A` |
| Fond principal | Crème | `#FDF6EC` |
| Fond très clair | Crème clair | `#FFF9F0` |
| Texte principal | Gris charbon | `#2C2C2C` |
| Texte secondaire | Gris doux | `#5F6470` |

Les couleurs Bootstrap (bleu, vert, rouge standard) sont remappées vers cette charte pour garantir la cohérence visuelle sur tous les composants.

---

## Typographies

| Usage | Police | Source |
|---|---|---|
| Titres | Playfair Display (400, 700) | Google Fonts |
| Corps de texte | Inter (300, 400, 500, 600) | Google Fonts |

Chargées via `@import` Google Fonts dans la feuille de style principale (`public/css/style.css`).

---

## Composants UI

**Boutons**
- Principal (`btn-vg`) : fond bordeaux `#8B1A2B`, texte blanc, hover bordeaux foncé.
- Contour (`btn-vg-outline`) : transparent, bordure bordeaux, hover fond bordeaux.
- Accent (`btn-or`) : fond or `#D4A843`, texte charbon `#2C2C2C` (contraste 6.3:1).
- Secondaire Bootstrap : contour gris doux, hover bordeaux.

**Cartes menus**
- Ombre légère, image en haut, titre Playfair Display, badge prix bordeaux.
- Indicateur de stock coloré : vert (disponible), orange (≤ 3 places), rouge (épuisé).

**Badges de statut commande**
- `en_attente` : fond crème-or, texte sombre.
- `accepte` / `en_preparation` / `en_cours_livraison` : variantes crème bordeaux.
- `terminee` : fond neutre clair.
- `annulee` : fond rouge très désaturé.

**Panneau de filtres**
- Fond crème `#FDF6EC`, bordure subtile, coins arrondis 8px.
- Bouton "Réinitialiser" en style `btn-vg-outline`, aligné à droite du titre.

---

## Contrastes WCAG AA

| Combinaison | Ratio | Conformité |
|---|---|---|
| Bordeaux sur blanc | 9.21:1 | ✅ AAA |
| Bordeaux sur crème | 8.58:1 | ✅ AAA |
| Texte charbon sur blanc | 13.97:1 | ✅ AAA |
| Gris doux sur blanc | 5.93:1 | ✅ AA |
| Texte charbon sur or | 6.31:1 | ✅ AA |

---

## Direction photographique

- Ambiance de table, réception ou préparation culinaire.
- Lumière chaude et conviviale, tons bordeaux, crème, bois naturel et accents dorés.
- Visuels simples, humains et professionnels.
- Format WebP pour toutes les images du site.

---

## Maquettes — Wireframes

### Desktop 1 — Accueil

```
+--------------------------------------------------------------+
|  Logo Vite & Gourmand    Menus | Contact | Connexion         |
+--------------------------------------------------------------+
|                                                              |
|          HERO : Vite & Gourmand, traiteur à Bordeaux         |
|                   depuis 25 ans                              |
|               [ Découvrir nos menus ]                        |
|                                                              |
+--------------------------------------------------------------+
|  25 ans d'expérience  |  Organisation maîtrisée  |  Qualité  |
+--------------------------------------------------------------+
|                   Ils nous font confiance                    |
|  ★★★★★ "Avis 1"    |  ★★★★☆ "Avis 2"   |  ★★★★★ "Avis 3"  |
+--------------------------------------------------------------+
|  Footer : Horaires | Liens | Mentions légales | CGV          |
+--------------------------------------------------------------+
```

### Desktop 2 — Liste des menus

```
+--------------------------------------------------------------+
|  Filtrer les menus          [ Réinitialiser ]                |
|  [ Prix min ] [ Prix max ] [ Thème ] [ Régime ] [ Pers. ]   |
+--------------------+--------------------+--------------------+
|  [Image menu]      |  [Image menu]      |  [Image menu]      |
|  Titre             |  Titre             |  Titre             |
|  Description...    |  Description...    |  Description...    |
|  Min. X personnes  |  Min. X personnes  |  Min. X personnes  |
|  ✅ X places dispo  |  ⚠ 2 places        |  ❌ Plus disponible |
|  Prix : XX,XX €    |  Prix : XX,XX €    |  Prix : XX,XX €    |
|  [ Voir le détail ]|  [ Voir le détail ]|  [ Voir le détail ]|
+--------------------+--------------------+--------------------+
```

### Desktop 3 — Formulaire de commande

```
+--------------------------------------------------------------+
|  Passer une commande                                         |
+--------------------------------------------------------------+
|  Prénom* [auto]    | Nom* [auto]                             |
|  Email* [auto]     | Téléphone* [auto]                       |
|  Adresse de livraison*                                       |
|  Ville*            | Code postal*                            |
|  Date de prestation*         | Heure*                        |
|  Menu sélectionné* [pré-rempli si venu du détail]            |
|  ⚠ Conditions importantes du menu (encadrées)                |
|  Nombre de personnes* (min. X)                               |
|  ───────────────────────────────────────────────             |
|  Prix menu : XX,XX €  Livraison : X,XX €  Total : XX,XX €   |
|                    [ Valider ma commande ]                   |
+--------------------------------------------------------------+
```

### Mobile 1 — Accueil

```
+-------------------------+
| ☰        Vite & Gourmand|
+-------------------------+
|  HERO                   |
|  Traiteur à Bordeaux    |
|  [ Découvrir nos menus ]|
+-------------------------+
|  ✓ 25 ans d'expérience  |
|  ✓ Organisation         |
|  ✓ Qualité              |
+-------------------------+
|  ★★★★★ "Avis 1"         |
|  ★★★★☆ "Avis 2"         |
+-------------------------+
|  Footer condensé        |
+-------------------------+
```

### Mobile 2 — Liste des menus

```
+-------------------------+
|  Tous les menus         |
|  [ Filtres empilés ]    |
|  [ Réinitialiser ]      |
+-------------------------+
|  [Image]                |
|  Titre menu             |
|  Min. X personnes       |
|  ✅ X places disponibles |
|  Prix : XX,XX €         |
|  [ Voir le détail ]     |
+-------------------------+
|  [Image]                |
|  ...                    |
+-------------------------+
```

### Mobile 3 — Espace client

```
+-------------------------+
|  Bonjour, Prénom !      |
+-------------------------+
|  Mes commandes          |
|  N° commande            |
|  Menu | Date            |
|  [badge statut]         |
|  [ Modifier ] [Annuler] |
+-------------------------+
|  Mes informations       |
|  Prénom / Nom           |
|  Téléphone / Adresse    |
|  [ Enregistrer ]        |
|  ─────────────────────  |
|  [ Supprimer mon compte]|
+-------------------------+
```
