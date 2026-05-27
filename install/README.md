# Installation — Traiteur SaaS

Ce guide décrit comment déployer l'application sur un nouveau serveur ou en local.

---

## Prérequis

| Composant | Version minimale |
|-----------|-----------------|
| PHP       | 8.1+            |
| MySQL     | 8.0+            |
| Composer  | 2.x             |
| Extensions PHP | pdo, pdo_mysql, mbstring, json, fileinfo |

---

## Installation en 5 étapes

### 1. Cloner le dépôt et installer les dépendances

```bash
git clone <url-du-repo> mon-traiteur
cd mon-traiteur
composer install --no-dev --optimize-autoloader
```

### 2. Configurer l'environnement

```bash
cp .env.example .env
```

Ouvrir `.env` et remplir **au minimum** :

```
DB_HOST=localhost
DB_NAME=nom_de_votre_base
DB_USER=utilisateur_mysql
DB_PASS=mot_de_passe_mysql
BASE_URL=https://montraiteur.fr
APP_ENV=production
```

Pour les paiements en ligne (Stripe), les emails (Brevo/SMTP) et le stockage des images
en production (Cloudinary), remplir également les sections correspondantes.

### 3. Lancer le script d'installation

```bash
php install/bootstrap.php
```

Le script effectue automatiquement :
- Vérification des prérequis PHP
- Création de la base de données si elle n'existe pas
- Application du schéma (`sql/schema.sql`)
- Application de toutes les migrations dans l'ordre (`sql/migrations/`)
- Création interactive du compte administrateur
- Création des dossiers d'upload

### 4. Configurer le serveur web

**Apache** — créer un VirtualHost pointant vers `public/` :

```apache
<VirtualHost *:80>
    DocumentRoot /var/www/mon-traiteur/public
    <Directory /var/www/mon-traiteur/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Le fichier `public/.htaccess` gère déjà la réécriture des URLs.

**Nginx** :

```nginx
root /var/www/mon-traiteur/public;
index index.php;
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
location ~ \.php$ {
    fastcgi_pass unix:/run/php/php8.1-fpm.sock;
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
}
```

**Railway / Docker** — `APP_ENV=production` dans les variables d'environnement Railway.
Le `Dockerfile` existant démarre PHP built-in server sur `public/`.

### 5. Personnaliser depuis l'interface admin

Se connecter sur `/admin` avec le compte créé à l'étape 3, puis :

1. **Paramètres → Identité** : nom du traiteur, slogan, couleurs
2. **Paramètres → Entreprise** : SIRET, adresse, IBAN (obligatoires pour la facturation)
3. **Admin → Images** : logo (favicon + navbar), bannière hero, photo équipe
4. **Admin → Personnaliser l'accueil** : sous-titre et paragraphe hero
5. **Paramètres → Pages légales** : CGV et Mentions légales personnalisées (optionnel)

---

## Mise à jour

Pour appliquer une nouvelle migration après une mise à jour du code :

```bash
git pull
composer install --no-dev --optimize-autoloader
php install/bootstrap.php
```

Le script est idempotent : il saute les migrations déjà appliquées et ne recrée pas
le compte admin s'il en existe déjà un.

---

## Variables d'environnement — référence complète

| Variable | Obligatoire | Description |
|----------|-------------|-------------|
| `DB_HOST` | ✓ | Hôte MySQL |
| `DB_NAME` | ✓ | Nom de la base |
| `DB_USER` | ✓ | Utilisateur MySQL |
| `DB_PASS` | ✓ | Mot de passe MySQL |
| `BASE_URL` | ✓ | URL publique (sans slash final) |
| `APP_ENV` | ✓ | `development` ou `production` |
| `STRIPE_SECRET_KEY` | paiements CB | Clé secrète Stripe |
| `STRIPE_PUBLISHABLE_KEY` | paiements CB | Clé publique Stripe |
| `STRIPE_WEBHOOK_SECRET` | paiements CB | Secret webhook Stripe |
| `MAIL_HOST` | emails | Hôte SMTP |
| `MAIL_PORT` | emails | Port SMTP (587 ou 465) |
| `MAIL_USER` | emails | Identifiant SMTP |
| `MAIL_PASS` | emails | Mot de passe SMTP |
| `MAIL_FROM` | emails | Expéditeur des emails |
| `CLOUDINARY_CLOUD_NAME` | prod uploads | Cloud Cloudinary |
| `CLOUDINARY_API_KEY` | prod uploads | Clé API Cloudinary |
| `CLOUDINARY_API_SECRET` | prod uploads | Secret API Cloudinary |

---

## Dépannage

**Erreur 500 au démarrage** : vérifier que `.env` existe et que `DB_*` sont corrects.

**Images non persistantes sur Railway** : configurer les variables `CLOUDINARY_*`.
Sans Cloudinary, les uploads sont locaux et perdus à chaque redéploiement.

**Emails non reçus** : vérifier `MAIL_*` dans `.env`. En développement, utiliser
Mailtrap. En production, utiliser Brevo (ex-Sendinblue) ou un SMTP dédié.

**Webhook Stripe non reçu** : l'URL du webhook doit être déclarée dans le
[Dashboard Stripe](https://dashboard.stripe.com/webhooks) :
`https://montraiteur.fr/stripe/webhook`
