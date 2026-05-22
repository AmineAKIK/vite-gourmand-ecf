# Manuel d'utilisation — Vite & Gourmand

## Présentation

Vite & Gourmand est une application web de commande de menus traiteur pour l'entreprise éponyme, basée à Bordeaux. Elle permet aux visiteurs de consulter les menus, aux clients authentifiés de passer et suivre des commandes, aux employés de gérer les commandes et les menus, et à l'administrateur de superviser l'ensemble.

**URL de l'application :** `https://vite-gourmand-ecf-production-c7ac.up.railway.app`

---

## Comptes de test

| Rôle | Email | Mot de passe |
|---|---|---|
| Administrateur | `admin@vitegourmand.fr` | `Admin@2024!` |
| Employé | Créer depuis l'espace admin | Au choix |
| Client | Créer depuis la page Inscription | Au choix |

> Le mot de passe doit contenir au minimum 10 caractères, une majuscule, une minuscule, un chiffre et un caractère spécial.

---

## Parcours visiteur (sans compte)

1. Ouvrir la page d'accueil — présentation de l'entreprise et avis clients validés.
2. Cliquer sur **Tous les menus** dans la navigation.
3. Utiliser les filtres (prix min/max, thème, régime, nombre de personnes) — mise à jour sans rechargement.
4. Cliquer sur **Réinitialiser** pour effacer tous les filtres.
5. Cliquer sur **Voir le détail** pour consulter composition, allergènes, conditions et stock.
6. Accéder à **Contact** pour envoyer un message à l'entreprise.
7. Consulter les **Mentions légales** et les **CGV** depuis le pied de page.

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
- **Modifier** : possible si statut **En attente** (adresse, date, heure, nombre de personnes).
- **Annuler** : possible si statut **En attente**.
- **Suivre** : disponible dès le statut **Accepté** — historique complet des changements.
- **Donner un avis** : disponible quand la commande est **Terminée** (note 1 à 5 + commentaire).
- **Modifier ses informations** personnelles depuis la carte "Mes informations".
- **Supprimer son compte** : bouton en bas de la carte, avec confirmation obligatoire.

---

## Parcours employé

1. Se connecter avec le compte employé fourni par l'administrateur.
2. Accéder à **l'Espace employé** depuis la navigation.

### Gestion des commandes
- Filtrer par **statut** ou **nom de client**.
- Dérouler une commande pour accéder aux actions.
- Transitions de statut autorisées :
  - `En attente` → `Accepté` → `En préparation` → `En cours de livraison` → `Livré` → `Terminée`
  - `Livré` → `En attente du retour de matériel` → `Terminée`
- **Annuler** : motif + mode de contact obligatoires (appel GSM ou mail).
- Statut `En attente du retour de matériel` → email de relance automatique au client.
- Statut `Terminée` → email automatique invitant le client à donner son avis.

### Gestion des menus et plats
- Créer, modifier ou supprimer un menu (titre, description, thème, régime, conditions, prix, stock, images).
- Créer, modifier ou supprimer un plat (titre, catégorie, allergènes, photo).
- Un plat utilisé dans un menu ne peut pas être supprimé directement.

### Modération des avis
- **Valider** un avis pour l'afficher sur la page d'accueil.
- **Refuser** un avis pour ne pas l'afficher.

### Horaires
- Modifier les horaires d'ouverture (lundi au dimanche) visibles dans le pied de page.

---

## Parcours administrateur

L'administrateur accède à toutes les fonctionnalités employé, plus :

### Gestion des employés (`/admin/employes`)
1. Créer un compte employé : email, prénom, nom, mot de passe.
2. L'employé reçoit un email de notification (mot de passe communiqué manuellement).
3. Désactiver ou réactiver un compte employé.

### Statistiques (`/admin/stats`)
- **Dashboard** : vue d'ensemble et graphique du nombre de commandes par menu (données MongoDB).
- **Statistiques CA** : chiffre d'affaires par menu avec filtres par menu et par période.

---

## Règles métier importantes

| Règle | Détail |
|---|---|
| Minimum de personnes | Nombre saisi ≥ minimum défini sur le menu |
| Réduction | −10 % si nombre de personnes ≥ minimum + 5 |
| Livraison Bordeaux | Gratuite |
| Livraison hors Bordeaux | 5,00 € + 0,59 €/km depuis Bordeaux |
| Modification commande | Uniquement si statut = En attente |
| Annulation employé | Motif + mode de contact obligatoires |
| Retour matériel | 10 jours ouvrés, pénalité 600 € sinon (cf. CGV) |
| Compte admin | Impossible à créer depuis l'application |
