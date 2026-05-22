# Manuel d'utilisation — Vite & Gourmand

## Présentation

Vite & Gourmand est une application web de commande de menus traiteur pour l'entreprise éponyme, basée à Bordeaux. Elle permet aux visiteurs de consulter les menus, aux clients authentifiés de passer et suivre des commandes, aux employés de gérer les commandes et les menus, et à l'administrateur de superviser l'ensemble.

**URL de l'application :** `https://vite-gourmand-ecf-production-c7ac.up.railway.app`

---

## Comptes de test

| Rôle | Email | Mot de passe |
|---|---|---|
| Administrateur | `admin@vitegourmand.fr` | `Admin@2024!` |
| Employé | Créer depuis l'espace admin | Au choix (politique mot de passe) |
| Client | Créer depuis la page Inscription | Au choix (politique mot de passe) |

> Le mot de passe doit contenir au minimum 10 caractères, une majuscule, une minuscule, un chiffre et un caractère spécial.

---

## Parcours visiteur (sans compte)

1. Ouvrir la page d'accueil — présentation de l'entreprise et avis clients validés.
2. Cliquer sur **Tous les menus** dans la navigation.
3. Utiliser les filtres (prix min/max, thème, régime, nombre de personnes) — les résultats se mettent à jour sans rechargement.
4. Cliquer sur **Voir le détail** pour consulter la composition, les allergènes, les conditions et le stock disponible.
5. Accéder à la page **Contact** pour envoyer un message à l'entreprise.
6. Consulter les **Mentions légales** et les **CGV** depuis le pied de page.

---

## Parcours client

### Création de compte
1. Cliquer sur **Connexion** puis **Créer un compte**.
2. Renseigner : prénom, nom, email, téléphone, adresse, ville, code postal et mot de passe.
3. Un email de bienvenue est envoyé à l'adresse renseignée.

### Commander un menu
1. Depuis la vue détaillée d'un menu, cliquer sur **Commander ce menu**.
2. Les informations personnelles sont pré-remplies depuis le compte.
3. Compléter : adresse de livraison, ville, code postal, date et heure de prestation.
4. Sélectionner le nombre de personnes (minimum imposé par le menu).
5. Le prix se met à jour automatiquement :
   - Livraison **gratuite** à Bordeaux.
   - Hors Bordeaux : **5 € + 0,59 €/km**.
   - Réduction de **10 %** si le nombre de personnes dépasse de 5 ou plus le minimum.
6. Vérifier le récapitulatif (prix menu + livraison + total) avant de valider.
7. Un email de confirmation est envoyé après validation.

### Gérer ses commandes (espace Mon compte)
- **Modifier** une commande : possible tant que le statut est **En attente** (adresse, date, heure, nombre de personnes).
- **Annuler** une commande : possible tant que le statut est **En attente**.
- **Suivre** une commande : disponible dès que le statut passe à **Accepté** — l'historique complet des statuts est affiché.
- **Donner un avis** : disponible quand la commande est **Terminée** (note de 1 à 5 + commentaire).
- **Modifier ses informations** personnelles depuis la carte "Mes informations".
- **Supprimer son compte** : bouton en bas de la carte "Mes informations", avec confirmation.

---

## Parcours employé

1. Se connecter avec le compte employé fourni par l'administrateur.
2. Accéder à l'**Espace employé** depuis la navigation.

### Gestion des commandes
- Filtrer les commandes par **statut** ou par **nom de client**.
- Cliquer sur une commande pour dérouler les détails et les actions.
- Changer le statut selon la progression :
  - `En attente` → `Accepté` → `En préparation` → `En cours de livraison` → `Livré` → `Terminée`
  - `Livré` → `En attente du retour de matériel` → `Terminée`
- **Annuler** une commande : obligatoire de renseigner un motif et un mode de contact (appel GSM ou mail).
- Quand le statut passe à `En attente du retour de matériel`, le client reçoit automatiquement un email de relance.
- Quand le statut passe à `Terminée`, le client reçoit un email l'invitant à donner son avis.

### Gestion des menus et plats
- Créer, modifier ou supprimer un menu (titre, description, thème, régime, conditions, prix, stock, images).
- Créer, modifier ou supprimer un plat (titre, catégorie, allergènes, photo).
- Un plat utilisé dans un menu ne peut pas être supprimé directement.

### Modération des avis
- Consulter les avis en attente de modération.
- **Valider** un avis pour l'afficher sur la page d'accueil.
- **Refuser** un avis pour ne pas l'afficher.

### Horaires
- Modifier les horaires d'ouverture (lundi au dimanche) affichés dans le pied de page.

---

## Parcours administrateur

L'administrateur accède à toutes les fonctionnalités employé, plus :

### Gestion des employés
1. Aller dans **Gérer les employés**.
2. Créer un compte employé en renseignant email, prénom, nom et mot de passe.
3. L'employé reçoit un email de notification (le mot de passe est communiqué manuellement).
4. Désactiver ou réactiver un compte employé (départ ou retour de l'employé).

### Statistiques
- **Dashboard** : vue d'ensemble des commandes et graphique du nombre de commandes par menu (données MongoDB).
- **Statistiques CA** : chiffre d'affaires par menu avec filtres par menu et par période.

---

## Règles métier importantes

| Règle | Détail |
|---|---|
| Minimum de personnes | Le nombre saisi doit être ≥ au minimum défini sur le menu |
| Réduction | −10 % si nombre de personnes ≥ minimum + 5 |
| Livraison Bordeaux | Gratuite |
| Livraison hors Bordeaux | 5,00 € + 0,59 €/km depuis Bordeaux |
| Modification commande | Uniquement si statut = En attente |
| Annulation employé | Motif + mode de contact obligatoires |
| Retour matériel | 10 jours ouvrés, pénalité 600 € sinon (cf. CGV) |
| Compte admin | Impossible à créer depuis l'application |
