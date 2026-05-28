# Guide d'installation — Tugères

Ce guide couvre le déploiement complet de Tugères pour un nouveau client traiteur,
de Railway jusqu'à la livraison d'un site opérationnel.

---

## Sommaire

1. [Prérequis comptes externes](#1-prérequis-comptes-externes)
2. [Déploiement Railway en 10 minutes](#2-déploiement-railway-en-10-minutes)
3. [Variables d'environnement — référence complète](#3-variables-denvironnement--référence-complète)
4. [Initialisation de la base de données](#4-initialisation-de-la-base-de-données)
5. [Configuration DNS et domaine custom](#5-configuration-dns-et-domaine-custom)
6. [Cloudinary — images persistantes](#6-cloudinary--images-persistantes)
7. [Brevo — emails transactionnels](#7-brevo--emails-transactionnels)
8. [Stripe — paiements en ligne](#8-stripe--paiements-en-ligne)
9. [Activation de la licence](#9-activation-de-la-licence)
10. [Personnalisation depuis l'interface admin](#10-personnalisation-depuis-linterface-admin)
11. [Mise à jour d'une instance existante](#11-mise-à-jour-dune-instance-existante)
12. [Dépannage](#12-dépannage)

---

## 1. Prérequis comptes externes

Avant de démarrer, créez (ou récupérez les accès à) ces services :

| Service | Usage | Gratuit/payant | Lien |
|---------|-------|----------------|------|
| **Railway** | Hébergement app + MySQL | Starter 5$/mois | railway.app |
| **GitHub** | Code source | Gratuit | github.com |
| **Cloudinary** | Stockage images | Free tier suffisant | cloudinary.com |
| **Brevo** | Emails transactionnels | 300 emails/jour gratuit | brevo.com |
| **Stripe** | Paiements CB | 0% fixe + 1.4% + 0.25€/tx | stripe.com |

> **Note :** Cloudinary et Stripe sont optionnels au démarrage.
> Sans Cloudinary, les images sont perdues à chaque redéploiement.
> Sans Stripe, seul le paiement par virement est disponible.

---

## 2. Déploiement Railway en 10 minutes

### 2.1 Créer le projet Railway

1. Aller sur [railway.app](https://railway.app) → **New Project**
2. Choisir **Deploy from GitHub repo**
3. Sélectionner le repository Tugères (ou faire un fork depuis le repo AkikSystems)
4. Railway détecte automatiquement le `Dockerfile` — cliquer **Deploy**

### 2.2 Ajouter MySQL

1. Dans le projet Railway → **+ New** → **Database** → **MySQL**
2. Railway crée automatiquement un service MySQL et expose les variables :
   `MYSQLHOST`, `MYSQLDATABASE`, `MYSQLUSER`, `MYSQLPASSWORD`, `MYSQLPORT`
3. Dans le service **app** → **Variables** → ajouter les variables de connexion
   (voir [section 3](#3-variables-denvironnement--référence-complète))

### 2.3 Variables minimales obligatoires

Dans Railway : service app → **Settings** → **Variables** → ajouter :

```
DB_HOST      = ${{MySQL.MYSQLHOST}}
DB_NAME      = ${{MySQL.MYSQLDATABASE}}
DB_USER      = ${{MySQL.MYSQLUSER}}
DB_PASS      = ${{MySQL.MYSQLPASSWORD}}
BASE_URL     = https://votre-domaine.fr
APP_ENV      = production
```

> Utiliser la syntaxe `${{MySQL.MYSQLHOST}}` pour référencer directement les variables
> du service MySQL Railway — elles se mettent à jour automatiquement.

### 2.4 Vérifier le déploiement

- Aller sur l'URL Railway fournie (`xxx.up.railway.app`)
- Le endpoint `/health` doit retourner `{"status":"ok","db":"ok"}`
- Si `"db":"down"` → les variables DB ne sont pas encore propagées, attendre 30s et réessayer

---

## 3. Variables d'environnement — référence complète

### Obligatoires

| Variable | Exemple | Description |
|----------|---------|-------------|
| `DB_HOST` | `containers-us-west-1.railway.app` | Hôte MySQL Railway |
| `DB_NAME` | `railway` | Nom de la base |
| `DB_USER` | `root` | Utilisateur MySQL |
| `DB_PASS` | `xxxx` | Mot de passe MySQL |
| `BASE_URL` | `https://montraiteur.fr` | URL publique sans slash final |
| `APP_ENV` | `production` | Mode (`production` ou `development`) |

### Emails — Brevo (recommandé)

| Variable | Exemple | Description |
|----------|---------|-------------|
| `MAIL_HOST` | `smtp-relay.brevo.com` | Serveur SMTP Brevo |
| `MAIL_PORT` | `587` | Port SMTP (587 TLS recommandé) |
| `MAIL_USER` | `votre@email.fr` | Identifiant SMTP Brevo |
| `MAIL_PASS` | `xxxx` | Clé SMTP Brevo (pas le mot de passe du compte) |
| `MAIL_FROM` | `contact@montraiteur.fr` | Expéditeur affiché |

> La clé SMTP Brevo se trouve dans **Brevo → SMTP & API → SMTP → Clé SMTP**.

### Paiements — Stripe (optionnel)

| Variable | Exemple | Description |
|----------|---------|-------------|
| `STRIPE_SECRET_KEY` | `sk_live_xxxx` | Clé secrète Stripe (sk_live en prod) |
| `STRIPE_PUBLISHABLE_KEY` | `pk_live_xxxx` | Clé publique (non utilisée côté serveur) |
| `STRIPE_WEBHOOK_SECRET` | `whsec_xxxx` | Secret webhook (voir section 8) |

### Images — Cloudinary (recommandé en production)

| Variable | Exemple | Description |
|----------|---------|-------------|
| `CLOUDINARY_CLOUD_NAME` | `montraiteur` | Nom du cloud Cloudinary |
| `CLOUDINARY_API_KEY` | `123456789` | Clé API Cloudinary |
| `CLOUDINARY_API_SECRET` | `xxxx` | Secret API Cloudinary |

> Sans ces variables, les images sont stockées dans `public/uploads/` qui est
> **éphémère sur Railway** (perdu à chaque redéploiement).

### Sécurité

| Variable | Exemple | Description |
|----------|---------|-------------|
| `CRON_SECRET` | `token-aleatoire-64-chars` | Token pour protéger `/cron/rappels` |

---

## 4. Initialisation de la base de données

### Méthode A — Script web (recommandée pour les non-téchniques)

Une fois les variables DB configurées et le déploiement effectué :

1. Aller sur `https://votre-domaine.fr/install/check.php`
   → Vérifie que tous les prérequis sont OK
2. Cliquer **Lancer l'installation** → `setup.php`
3. Remplir le formulaire (identifiants DB déjà pré-remplis depuis les variables env)
4. Cliquer **Installer** — le schéma et les migrations s'appliquent automatiquement

> **Important :** `setup.php` crée un fichier verrou `.installed` après exécution.
> Il devient inaccessible pour éviter toute réinstallation accidentelle.

> **Note Railway :** Le dossier `install/` n'est pas exposé via le web car
> le document root Docker pointe sur `public/`. Le wizard est donc accessible
> uniquement si vous copiez ces fichiers dans `public/install/` pour un déploiement
> one-shot, puis les supprimez. Sur VPS Apache, protégez ce dossier avec le `.htaccess` inclus.

### Méthode B — Script CLI (recommandée pour les développeurs)

Depuis un terminal avec accès SSH au container ou en local :

```bash
php install/bootstrap.php
```

Suit une procédure interactive en 6 étapes (vérifications, DB, migrations, admin, licence).

### Méthode C — Migrations manuelles

```bash
# 1. Appliquer le schéma de base
mysql -u $DB_USER -p$DB_PASS $DB_NAME < sql/schema.sql

# 2. Appliquer toutes les migrations dans l'ordre
for f in sql/migrations/*.sql; do
  echo "Applying $f..."
  mysql -u $DB_USER -p$DB_PASS $DB_NAME < "$f"
done

# 3. Insérer le compte admin (copier seed_admin.sql.example, remplir, exécuter)
cp sql/seed_admin.sql.example sql/seed_admin.sql
# Éditer seed_admin.sql avec email/hash bcrypt
mysql -u $DB_USER -p$DB_PASS $DB_NAME < sql/seed_admin.sql
```

---

## 5. Configuration DNS et domaine custom

### Sur Railway

1. Service app → **Settings** → **Domains** → **+ Custom Domain**
2. Saisir le domaine : `montraiteur.fr`
3. Railway affiche un enregistrement CNAME à créer chez votre registrar

### Chez votre registrar (ex: OVH, Gandi, Namecheap)

Créer un enregistrement DNS :
```
Type  : CNAME
Nom   : @  (ou www pour sous-domaine)
Valeur: <valeur fournie par Railway>
TTL   : 3600
```

> La propagation DNS prend 5 à 60 minutes.
> Railway gère automatiquement le certificat SSL Let's Encrypt.

### Mettre à jour BASE_URL

Après activation du domaine custom, mettre à jour `BASE_URL` dans les variables Railway :
```
BASE_URL = https://montraiteur.fr
```

Redéployer le service (ou attendre le prochain déploiement automatique).

---

## 6. Cloudinary — images persistantes

### Pourquoi c'est nécessaire

Railway utilise un filesystem éphémère : à chaque redéploiement, les fichiers uploadés
dans `public/uploads/` sont supprimés. Cloudinary stocke les images de façon permanente
et les sert via CDN.

### Créer un compte Cloudinary

1. S'inscrire sur [cloudinary.com](https://cloudinary.com) (free tier : 25GB stockage, 25GB bande passante/mois)
2. Aller dans **Dashboard** → copier :
   - **Cloud Name**
   - **API Key**
   - **API Secret**
3. Ajouter ces 3 valeurs dans les variables Railway

### Vérifier le fonctionnement

- Aller dans **Admin → Paramètres** → uploader un logo
- Si l'URL de l'image contient `cloudinary.com/` → Cloudinary est actif
- Si l'URL contient `/uploads/` → l'image est stockée localement (Cloudinary non configuré)

---

## 7. Brevo — emails transactionnels

### Créer un compte Brevo

1. S'inscrire sur [brevo.com](https://www.brevo.com)
2. Vérifier le domaine expéditeur : **Settings → Senders & IPs → Domains**
3. Aller dans **SMTP & API → SMTP** → générer une **clé SMTP**
4. Récupérer les paramètres SMTP :
   - Host : `smtp-relay.brevo.com`
   - Port : `587`
   - Login : votre email de compte Brevo
   - Password : la **clé SMTP** générée (pas votre mot de passe)

### Tester les emails

Après configuration, aller dans **Admin → Paramètres** → envoyer un email test,
ou créer un compte test et déclencher une commande.

### Emails envoyés par Tugères

| Déclencheur | Template |
|-------------|----------|
| Inscription | Vérification email + lien |
| Mot de passe oublié | Lien de réinitialisation |
| Commande confirmée | Récapitulatif commande |
| Changement de statut commande (terminée) | Notification client |
| Relance matériel (en_attente_materiel) | Email relance |
| Rappel automatique J+2 et J+7 | Cron `/cron/rappels` |

---

## 8. Stripe — paiements en ligne

### Créer les clés API Stripe

1. Se connecter sur [dashboard.stripe.com](https://dashboard.stripe.com)
2. **Developers → API Keys** → copier :
   - `Secret key` (sk_live_xxx) → `STRIPE_SECRET_KEY`
   - `Publishable key` (pk_live_xxx) → `STRIPE_PUBLISHABLE_KEY`

### Configurer le webhook Stripe

1. **Developers → Webhooks** → **+ Add endpoint**
2. URL : `https://montraiteur.fr/stripe/webhook`
3. Événements à écouter : `checkout.session.completed`
4. Copier le **Signing secret** (`whsec_xxx`) → `STRIPE_WEBHOOK_SECRET`

> Le webhook est un filet de sécurité : si le client ferme la fenêtre après paiement
> avant la redirection, Stripe envoie quand même l'événement et la commande est créée.

### Mode test vs production

- Utiliser `sk_test_xxx` / `pk_test_xxx` pendant les tests
- Basculer sur `sk_live_xxx` / `pk_live_xxx` en production
- Les webhooks test et live ont des secrets différents
- Tester avec la carte Stripe test : `4242 4242 4242 4242`, exp. quelconque, CVC quelconque

---

## 9. Activation de la licence

Tugères affiche un bandeau d'avertissement si la licence n'est pas activée.

### Via le script d'installation (setup.php ou bootstrap.php)

La clé est demandée lors de l'installation et activée automatiquement.

### Via l'interface admin

1. Se connecter sur `/admin/parametres`
2. Section **Licence** → saisir la clé reçue d'AkikSystems

### Via base de données (manuel)

```sql
-- Remplacer par les vraies valeurs
SET @domain = 'montraiteur.fr';
SET @key    = 'VOTRE-CLE-LICENCE';
SET @secret = CONCAT('tugeres_akiksystems_2025_', @key);
SET @hash   = SHA2(CONCAT(@domain, @secret), 256); -- approximatif

INSERT INTO site_config (cle, valeur) VALUES ('license_key',    @key)    ON DUPLICATE KEY UPDATE valeur = VALUES(valeur);
INSERT INTO site_config (cle, valeur) VALUES ('license_domain', @domain) ON DUPLICATE KEY UPDATE valeur = VALUES(valeur);
```

> Pour la valeur exacte du hash HMAC, utiliser le script PHP fourni par AkikSystems.

---

## 10. Personnalisation depuis l'interface admin

Après installation, effectuer ces étapes dans cet ordre :

### Étape 1 — Identité (obligatoire)
`/admin/parametres` → section Identité :
- Nom du traiteur
- Slogan
- Couleur principale (bordeaux par défaut) et secondaire (or)
- Email de contact, téléphone, adresse

### Étape 2 — Entreprise (obligatoire pour la facturation)
`/admin/parametres` → section Entreprise :
- SIRET
- Forme juridique (SARL, SAS, Auto-entrepreneur…)
- Capital social
- IBAN (pour les virements)
- Numéro de TVA intracommunautaire

### Étape 3 — Images
`/admin/parametres` → section Images :
- Logo navbar (recommandé : SVG ou PNG transparent, largeur ~200px)
- Favicon (recommandé : PNG carré 64×64px)
- Photo hero page d'accueil
- Photo équipe

### Étape 4 — Menus et plats
`/employe/menus` → créer les menus avec :
- Titre, description, prix par personne, TVA applicable
- Image (uploadée vers Cloudinary si configuré)
- Plats associés

### Étape 5 — Géolocalisation livraison
`/admin/parametres` → section Livraison :
- Adresse de référence du traiteur
- Rayon de livraison (km)
- Prix par km (défaut : 0.59€)
- Frais fixes de livraison

### Étape 6 — Modes de paiement
`/admin/parametres` → section Paiement :
- Activer/désactiver les modes (CB Stripe, virement, chèque, espèces)

### Étape 7 — Employés
`/admin/employes` → créer les comptes employés :
- Email + prénom + nom
- Un mot de passe temporaire est envoyé par email
- L'employé est invité à le changer à la première connexion

---

## 11. Mise à jour d'une instance existante

### Déploiement Railway (automatique)

Si le repo GitHub est connecté à Railway avec auto-deploy activé,
chaque `git push` sur `main` déclenche automatiquement un redéploiement.

Les migrations sont appliquées automatiquement au démarrage via `ensureSchema()`
dans les models — aucune action manuelle requise dans la plupart des cas.

### Appliquer une migration manuelle (si nécessaire)

```bash
# Via Railway CLI
railway run php install/bootstrap.php

# Ou directement via MySQL
railway connect MySQL
# puis dans le shell MySQL :
source /path/to/migration.sql
```

### Versionner les mises à jour

Chaque mise à jour de Tugères est versionnée. Le numéro de version est visible
dans la sidebar workspace. Consulter le CHANGELOG pour les migrations associées.

---

## 12. Dépannage

### L'application ne démarre pas (500 au démarrage)

1. Vérifier les logs Railway : service → **Deployments** → cliquer le déploiement → **View Logs**
2. Causes fréquentes :
   - `DB_HOST` / `DB_NAME` / `DB_USER` / `DB_PASS` manquants ou incorrects
   - `vendor/autoload.php` absent (Composer non exécuté) — normalement géré par le Dockerfile
   - Extension PHP manquante — vérifier le Dockerfile

### `/health` retourne `"db":"down"`

- Les variables de connexion DB ne sont pas encore propagées → attendre 30s et recharger
- Le service MySQL Railway est arrêté → vérifier dans Railway Dashboard
- Erreur de réseau interne Railway → redéployer le service

### Images non persistantes (perdues après redéploiement)

Configurer Cloudinary (voir section 6). C'est le comportement normal sans Cloudinary
sur Railway car le filesystem est éphémère.

### Emails non reçus

1. Vérifier que `MAIL_HOST`, `MAIL_PORT`, `MAIL_USER`, `MAIL_PASS`, `MAIL_FROM` sont définis
2. Vérifier les spams du destinataire
3. Dans Brevo → **Logs** → vérifier si l'email a bien été envoyé
4. Vérifier que le domaine expéditeur est vérifié dans Brevo (SPF + DKIM)

### Webhook Stripe non reçu

1. Vérifier que l'URL `https://montraiteur.fr/stripe/webhook` est déclarée dans Stripe Dashboard
2. Vérifier que `STRIPE_WEBHOOK_SECRET` correspond bien au secret de CET endpoint
3. Tester avec la CLI Stripe : `stripe listen --forward-to https://montraiteur.fr/stripe/webhook`

### Paiement Stripe en mode test en production

Symptôme : les paiements sont acceptés avec la carte `4242...` mais pas les vraies cartes.
Cause : `STRIPE_SECRET_KEY` contient une clé `sk_test_` au lieu de `sk_live_`.
Correction : remplacer par les clés live dans les variables Railway + reconfigurer le webhook live.

### Login impossible malgré les bons identifiants

1. Si le message est "Email non vérifié" → le compte a été créé avec vérification email activée.
   Solution rapide : `UPDATE utilisateur SET email_verified_at = NOW() WHERE email = 'xxx'`
2. Si le message est "Compte bloqué" → rate limiting actif (5 tentatives / 15 min).
   Solution : `DELETE FROM rate_limit WHERE ip = 'xxx'` ou attendre 15 minutes.

### Accès `/admin` retourne 404 ou redirige vers /connexion

- Vérifier que l'utilisateur a bien `role_id = 3` dans la table `utilisateur`
- Vérifier que la session est active (cookies acceptés par le navigateur)

### Réinitialiser le mot de passe admin en cas de perte d'accès

```sql
-- Générer un hash bcrypt avec PHP
-- php -r "echo password_hash('NouveauMotDePasse', PASSWORD_BCRYPT, ['cost'=>12]);"

UPDATE utilisateur
SET password = '$2y$12$votre_hash_ici'
WHERE email = 'admin@montraiteur.fr';
```
