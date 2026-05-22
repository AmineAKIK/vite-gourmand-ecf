# Vite & Gourmand

Application web de commande de menus traiteur — ECF TP Développeur Web et Web Mobile.

**Application en ligne :** `https://vite-gourmand-ecf-production-c7ac.up.railway.app`

---

## Stack technique

| Couche | Technologie |
|---|---|
| Serveur | PHP 8.2 built-in (`php -S`) |
| Front-end | HTML5, CSS3, Bootstrap 5.3, JavaScript ES6 |
| Back-end | PHP 8.2, architecture MVC maison |
| Base relationnelle | MySQL 8 via PDO |
| Base non relationnelle | MongoDB (statistiques commandes) |
| Emails | PHPMailer |
| Déploiement | Railway (Docker) |

---

## Installation en local

### Prérequis

- PHP >= 8.2 avec extensions : `pdo_mysql`, `mbstring`, `zip`, `mongodb`
- MySQL >= 8.0
- Composer

### Étapes

```bash
# 1. Installer les dépendances
composer install

# 2. Configurer l'environnement
cp .env.example .env
# Éditer .env avec vos paramètres

# 3. Créer la base de données
mysql -u root -p -e "CREATE DATABASE vite_gourmand CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p vite_gourmand < sql/vite_gourmand.sql

# 4. Lancer le serveur
php -S localhost:8080 -t public/
```

Accès : `http://localhost:8080`

---

## Compte administrateur initial

| Email | Mot de passe |
|---|---|
| `admin@vitegourmand.fr` | `Admin@2024!` |

Les comptes employés sont créés depuis l'espace administrateur.
Les comptes clients sont créés depuis la page d'inscription.

---

## Structure du projet

```
vite-gourmand/
├── Dockerfile               # Image PHP 8.2-cli pour Railway
├── railway.json             # Configuration déploiement Railway
├── composer.json            # Dépendances PHP
├── .env.example             # Modèle de configuration
├── public/                  # Racine web (index.php, css/, js/, images/)
├── sql/                     # Schéma et données initiales MySQL
├── docs/                    # Documents ECF (charte, manuel, technique, gestion)
└── src/
    ├── config/              # Configuration et connexion base de données
    ├── controllers/         # Contrôleurs MVC
    ├── models/              # Modèles d'accès aux données
    ├── services/            # Services métier (Mail, Stats, Commande)
    ├── views/               # Templates PHP (layouts, pages, partials)
    └── helpers.php          # Fonctions utilitaires globales
```

---

## Sécurité

- Mots de passe hashés en bcrypt (coût 12)
- Protection CSRF sur tous les formulaires POST
- Requêtes SQL préparées (PDO) sur 100 % des accès base
- Sanitisation des sorties HTML (`htmlspecialchars`)
- Contrôle d'accès par rôle (client / employé / administrateur)
- Aucun compte administrateur créable depuis l'application

---

## Documents ECF

Les sources sont dans `docs/` :

| Fichier | Contenu |
|---|---|
| `charte-graphique.md` | Palette, typographies, composants, wireframes |
| `manuel-utilisation.md` | Guide des parcours utilisateur avec identifiants |
| `gestion-projet.md` | Méthode agile, Trello, branches Git, risques |
| `documentation-technique.md` | Stack, MCD, diagrammes, déploiement Railway |
