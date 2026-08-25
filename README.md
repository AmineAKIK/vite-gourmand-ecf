# Tugères

Tugères est une application white-label de gestion et de commande pour traiteurs, éditée par AkikSystems. Le modèle de déploiement actuel privilégie **une instance isolée par traiteur** : application, base MySQL, configuration, domaine et licence séparés.

Le dépôt provient du projet ECF « Vite & Gourmand », mais son cycle d’exploitation actuel est celui du produit Tugères.

## Capacités principales

- catalogue et commandes traiteur ;
- capacité/quota de commandes et contrôle de disponibilité ;
- paiement Stripe avec tentative durable, webhook autoritaire et réconciliation ;
- suivi d’état des commandes ;
- stock, recettes et ledger de mouvements ;
- devis, factures, acomptes, avoirs, PDF et envoi email ;
- signature de devis ;
- paiements et remboursements comptabilisés dans un ledger ;
- statistiques et exports ;
- personnalisation white-label ;
- licence / entitlements Tugères ;
- rappels automatisables ;
- purge de données opérationnelles.

## Stack

| Couche | Technologie |
|---|---|
| Runtime | PHP 8.2 + Apache dans Docker |
| Front-end | HTML5, CSS3, Bootstrap 5.3, JavaScript |
| Back-end | PHP, MVC maison + services/domain policies |
| Base | MySQL 8 via PDO |
| Paiement | Stripe |
| Email | API HTTP Brevo |
| Images | Cloudinary recommandé en production |
| PDF | Dompdf |
| Déploiement | image Docker ; configuration Railway présente dans le dépôt |
| CI | GitHub Actions Quality Gate + build image production |

## Installation locale

Prérequis : PHP 8.2, Composer et MySQL 8.

```bash
composer install
cp .env.example .env
# renseigner au minimum DB_HOST, DB_NAME, DB_USER et DB_PASS
php bin/migrate.php
php -S localhost:8080 -t public/
```

Le serveur PHP intégré est uniquement un moyen pratique de développement local. La production utilise l’image Docker/Apache.

## Déploiement

L’image de production exécute les migrations **avant** le démarrage d’Apache via `docker/entrypoint.sh`. Une erreur de migration permanente empêche le serveur de démarrer.

Deux endpoints sont disponibles :

- `/health` : liveness applicative ;
- `/ready` : readiness MySQL + `schema_migrations`, à utiliser pour décider si l’instance est réellement exploitable.

Le schéma ne doit pas être muté pendant une requête HTTP. Garder `TUGERES_ALLOW_HTTP_MIGRATIONS=false` en production.

Consulter **[docs/tugeres-operations.md](docs/tugeres-operations.md)** pour le runbook complet : variables d’environnement, Stripe, Brevo, licences, migrations, cron, rétention, sauvegarde/restauration, upgrade/rollback et checklist go-live.

## Migrations

Les migrations sont dans `sql/migrations/` et sont suivies dans `schema_migrations` avec checksum SHA-256. Une migration déjà appliquée ne doit jamais être modifiée : toute évolution passe par un nouveau fichier numéroté.

Exécution manuelle contrôlée :

```bash
php bin/migrate.php
```

Voir `sql/migrations/README.md` pour le contrat détaillé du migrateur.

## Configuration de production

La source de référence des variables est `.env.example`. Les points critiques sont notamment :

- MySQL : `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` ;
- Stripe : `STRIPE_SECRET_KEY`, `STRIPE_PUBLISHABLE_KEY`, `STRIPE_WEBHOOK_SECRET` ;
- Brevo : `BREVO_API_KEY`, `MAIL_FROM` ;
- URL : `BASE_URL`, `APP_ENV=production` ;
- migrations : `TUGERES_ALLOW_HTTP_MIGRATIONS=false` ;
- licence : `TUGERES_ENTITLEMENTS_MODE`, `TUGERES_LICENSE_PUBLIC_KEY_B64` ;
- images : variables Cloudinary ;
- proxy : `TRUST_PROXY_HEADERS` uniquement après validation du chemin proxy.

## Facturation et stockage

Les documents de facturation privés sont stockés sous `storage/facturation`. Sur une plateforme à filesystem éphémère, leur persistance doit être explicitement assurée et testée après redéploiement avant ouverture d’un pilote professionnel.

Les images métier doivent utiliser un stockage durable tel que Cloudinary en production.

## Licence commerciale

Une licence signée peut être générée hors de l’instance client :

```bash
php bin/sign-license.php private-key.pem <license-id> <domain> <starter|pro|premium> [expires-at] > license.json
```

Puis installée sur l’instance :

```bash
php bin/install-license.php license.json
```

La clé privée de signature reste hors des déploiements clients.

## Maintenance

Dry-run de la purge opérationnelle :

```bash
php bin/prune-operational-data.php
```

Application effective :

```bash
php bin/prune-operational-data.php --apply
```

Une sauvegarde MySQL restaurable reste une responsabilité d’exploitation et doit être testée avant un go-live.

## Qualité

Le Quality Gate CI vérifie notamment :

- validation Composer et audit des dépendances ;
- syntaxe PHP ;
- tests unitaires ;
- PHPStan ;
- style des nouveaux fichiers PHP ;
- build de l’image Docker de production.

Une CI verte ne remplace pas les smoke tests sur l’environnement réellement déployé.

## Documentation historique

Le dossier `docs/` contient encore des documents issus de l’ECF et de l’historique Vite & Gourmand. Ils peuvent servir de référence fonctionnelle, mais **le runbook Tugères et le code courant font autorité pour l’exploitation de production**.
