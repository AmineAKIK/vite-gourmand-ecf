# Vite & Gourmand

Application web de commande de menus traiteur pour l'entreprise Vite & Gourmand, Bordeaux.

---

## Stack technique

| Composant | Technologie |
|---|---|
| Front-end | HTML5, CSS3, Bootstrap 5.3, JavaScript ES6 |
| Back-end | PHP 8.1+, Architecture MVC |
| Base de données | MySQL 8 (PDO), MongoDB pour les statistiques commandes/menu |
| Emails | PHPMailer |

---

## Installation en local

### Prérequis
- PHP >= 8.1 avec extensions : `php-mysql`, `php-mbstring`, `php-xml`, `php-zip`
- MySQL >= 8.0
- MongoDB local ou MongoDB Atlas pour les graphiques NoSQL
- Composer

### 1. Installer les dépendances PHP
```bash
composer install
```

### 2. Configuration
```bash
cp .env.example .env
# Modifier .env avec vos paramètres DB et mail
```

### 3. Base de données
```bash
mysql -u root -p -e "CREATE DATABASE vite_gourmand CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p vite_gourmand < sql/vite_gourmand.sql
```

### 4. Serveur de développement
```bash
php -S localhost:8080 -t public/
```

Accès : http://localhost:8080

---

## Compte initial

| Rôle | Email | Mot de passe |
|---|---|---|
| Administrateur | admin@vitegourmand.fr | Admin@2024! |

Les comptes employés sont créés depuis l'espace administrateur.
Les comptes utilisateurs sont créés depuis la page d'inscription.

---

## Structure du projet

```
vite-gourmand/
├── public/              # Point d'entrée web (index.php, css/, js/)
├── docs/                # Documents sources ECF à exporter en PDF
├── sql/                 # Schéma et données SQL
├── src/
│   ├── config/          # Configuration (Database, constantes)
│   ├── controllers/     # Contrôleurs MVC
│   ├── models/          # Modèles (accès données)
│   ├── services/        # Services (MailService)
│   ├── views/           # Templates PHP
│   └── helpers.php      # Fonctions utilitaires
├── .env.example
└── composer.json
```

---

## Sécurité

- Mots de passe hashés avec `password_hash()` (bcrypt cost 12)
- Protection CSRF sur tous les formulaires POST
- Validation et sanitisation des entrées
- Requêtes préparées PDO
- Contrôle d'accès par rôles (client / employé / administrateur)

---

## Documents ECF

Les sources des documents attendus sont dans `docs/` :

- `manuel-utilisation.md`
- `charte-graphique.md`
- `gestion-projet.md`
- `documentation-technique.md`

Ces fichiers peuvent être exportés en PDF depuis un éditeur Markdown ou un navigateur.
