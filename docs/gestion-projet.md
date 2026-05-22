# Documentation de gestion de projet

## Méthode

La gestion du projet est inspirée d'une méthode agile légère. Les fonctionnalités sont découpées en tâches courtes, priorisées selon leur valeur pour la livraison ECF et leur importance dans le cahier des charges.

## Outil

Un tableau de suivi peut être créé dans Trello, Notion ou Jira avec les colonnes suivantes :

- Backlog
- À faire
- En cours
- En test
- Terminé
- À corriger

## Découpage recommandé

- Initialisation du projet et environnement local.
- Modélisation relationnelle.
- Pages publiques : accueil, menus, détail, contact.
- Authentification et rôles.
- Commande client.
- Espace utilisateur.
- Espace employé.
- Espace administrateur.
- Statistiques NoSQL.
- Sécurité, RGPD, accessibilité.
- Déploiement.
- Documentation et préparation de soutenance.

## Branches Git recommandées

- `main` : version stable déployable.
- `develop` : intégration des fonctionnalités testées.
- `feature/auth`
- `feature/menus`
- `feature/commandes`
- `feature/admin-stats`
- `feature/docs`

## Exemples de critères d'acceptation

- Un visiteur peut filtrer les menus sans rechargement de page.
- Un client ne peut pas commander en dessous du minimum de personnes.
- Une commande acceptée ne peut plus être modifiée par le client.
- Un employé doit indiquer un motif et un mode de contact pour annuler.
- Un administrateur peut désactiver un compte employé.
- Les statistiques de commandes par menu sont lues depuis un stockage non relationnel.

## Risques identifiés

- Dépendances Composer absentes en local.
- Configuration SMTP nécessaire pour les mails réels.
- MongoDB doit être configuré pour alimenter les statistiques demandées.
- Export PDF des documents à réaliser avant rendu.
- Dépôt Git à initialiser proprement si le dossier local n'est pas déjà un vrai dépôt.
