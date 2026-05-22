# Documentation de gestion de projet — Vite & Gourmand

## Méthode

La gestion du projet est organisée selon une méthode **agile légère**. Les fonctionnalités ont été découpées en tâches courtes, priorisées selon le cahier des charges ECF, puis réparties en branches Git indépendantes mergées vers `develop` au fil de l'avancement.

---

## Outil de suivi

**Trello** — tableau disponible à l'adresse : `https://trello.com/b/EGkFxUax/vite-gourmand-ecf`

Colonnes utilisées :
- **Backlog** — toutes les fonctionnalités identifiées au départ
- **À faire** — sprint en cours
- **En cours** — tâche active
- **En test** — fonctionnalité développée, vérification en cours
- **Terminé** — validé et mergé

---

## Découpage en branches Git

| Branche | Contenu |
|---|---|
| `feature/auth` | Inscription, connexion, réinitialisation mot de passe, mails |
| `feature/menus` | Vue globale, filtres dynamiques AJAX, vue détaillée, galerie |
| `feature/commandes` | Formulaire de commande, calcul livraison GPS, réduction 10 % |
| `feature/espace-utilisateur` | Espace client, suivi, modification, annulation, avis |
| `feature/espace-employe` | Gestion commandes/statuts, menus/plats/horaires, avis |
| `feature/admin` | Gestion employés, stats CA, graphique MongoDB |
| `feature/securite` | CSRF, bcrypt, PDO préparé, contrôle d'accès par rôle, RGPD, RGAA |
| `feature/docs` | Charte graphique, manuel, doc technique, gestion projet |

Chaque branche est issue de `develop`. Après validation, merge vers `develop` avec l'option `--no-ff` pour conserver l'historique. Une fois `develop` stable, merge vers `main` (release v1.0.0).

---

## Historique Git (extrait)

```
release: v1.0.0 - merge develop → main
merge: feature/docs → develop
merge: feature/securite → develop
merge: feature/admin → develop
merge: feature/espace-employe → develop
merge: feature/espace-utilisateur → develop
merge: feature/commandes → develop
merge: feature/menus → develop
merge: feature/auth → develop
```

---

## Dépôt GitHub

**URL :** `https://github.com/AmineAKIK/vite-gourmand-ecf`

Branches présentes :
- `main` — version stable déployée sur Railway
- `develop` — branche d'intégration

---

## Déploiement

**Plateforme :** Railway (`https://railway.app`)

**URL de production :** `https://vite-gourmand-ecf-production-c7ac.up.railway.app`

Services déployés sur Railway :
- **vite-gourmand-ecf** — application PHP 8.2 via serveur built-in (`php -S`)
- **MySQL** — base de données relationnelle Railway

MongoDB utilisé via **MongoDB Atlas** (service cloud externe, configurable via variable `MONGO_URI`).

---

## Risques identifiés et réponses apportées

| Risque | Réponse |
|---|---|
| Conflit MPM Apache sur Railway | Abandonné, remplacé par `php -S` (serveur built-in PHP) |
| Extension MongoDB incompatible | `pecl install mongodb-2.1.0` + `mongodb/mongodb ^2.0` dans Composer |
| MongoDB indisponible en prod | Dégradation gracieuse : app fonctionne sans MongoDB, stats vides |
| Mails en spam | `AltBody` sur tous les mails, SMTP Gmail authentifié |
| Port Railway dynamique | `CMD php -S 0.0.0.0:${PORT:-8080}` dans le Dockerfile |

---

## Critères d'acceptation retenus

- Un visiteur filtre les menus sans rechargement de page (AJAX).
- Un client ne peut pas commander en dessous du minimum de personnes.
- La réduction de 10 % s'applique automatiquement dès minimum + 5 personnes.
- Une commande acceptée ne peut plus être modifiée ou annulée par le client.
- Un employé doit indiquer un motif et un mode de contact pour annuler.
- Un administrateur peut désactiver un compte employé.
- Les statistiques de commandes par menu sont lues depuis MongoDB.
- Tous les formulaires POST sont protégés par un token CSRF.
- Aucun compte administrateur ne peut être créé depuis l'application.
