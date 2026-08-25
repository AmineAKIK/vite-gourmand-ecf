# Tugères — Runbook d’exploitation

Ce document décrit l’exploitation d’une instance Tugères dédiée à un traiteur. Le modèle commercial visé est **une instance isolée par client** : code, base MySQL, configuration, domaine et licence propres à chaque traiteur.

## 1. Pré-requis de production

Une instance commerciale doit disposer au minimum de :

- une base MySQL 8 accessible depuis l’application ;
- un domaine HTTPS stable utilisé comme `BASE_URL` ;
- Stripe configuré avec une clé secrète, une clé publique et un secret de webhook ;
- Brevo configuré avec une clé API et une adresse expéditrice validée ;
- un stockage persistant pour les données qui ne doivent pas disparaître lors d’un redéploiement ;
- une stratégie de sauvegarde et de restauration MySQL testée ;
- une licence Tugères signée pour les déploiements commerciaux en mode `signed`.

Le filesystem d’un conteneur Railway peut être remplacé lors d’un redéploiement. Les archives de facturation privées sont écrites sous `storage/facturation`. Avant un pilote payant, vérifier qu’un volume persistant couvre ce chemin ou qu’une autre stratégie de persistance durable est effectivement en place. Ne pas considérer la conservation des documents comme validée tant que ce point n’est pas testé après redéploiement.

## 2. Variables d’environnement

La source de référence est `.env.example`.

### Base de données

| Variable | Production | Rôle |
|---|---:|---|
| `DB_HOST` | obligatoire | Hôte MySQL |
| `DB_NAME` | obligatoire | Base de l’instance |
| `DB_USER` | obligatoire | Utilisateur MySQL dédié |
| `DB_PASS` | obligatoire | Mot de passe MySQL |

### Application

| Variable | Production | Rôle |
|---|---:|---|
| `APP_ENV` | `production` | Désactive l’affichage des erreurs |
| `BASE_URL` | obligatoire | URL publique canonique, sans slash final |
| `TUGERES_ALLOW_HTTP_MIGRATIONS` | `false` | Interdit les migrations depuis une requête HTTP |
| `MIGRATION_STARTUP_ATTEMPTS` | `5` par défaut | Nombre de tentatives de connexion MySQL au démarrage |
| `TRUST_PROXY_HEADERS` | `false` par défaut | N’accepter les en-têtes proxy qu’après validation du chemin réseau |
| `SAAS_SECRET` | selon usage | Protège l’interface `admin-saas/` héritée |

`PORT` est fourni par la plateforme d’hébergement et consommé par l’entrypoint Docker.

### Stripe

| Variable | Production | Rôle |
|---|---:|---|
| `STRIPE_SECRET_KEY` | obligatoire si paiement CB | Clé serveur Stripe |
| `STRIPE_PUBLISHABLE_KEY` | obligatoire si paiement CB | Clé publique Stripe |
| `STRIPE_WEBHOOK_SECRET` | obligatoire | Vérification de `/stripe/webhook` |

Le webhook Stripe doit cibler `POST /stripe/webhook`. Le traitement webhook est l’autorité pour la confirmation du paiement et le fulfillment de la commande durable. La page `/stripe/success` réconcilie le retour navigateur mais ne doit pas être considérée comme la preuve primaire du paiement.

### Email / Brevo

| Variable | Production | Rôle |
|---|---:|---|
| `BREVO_API_KEY` | obligatoire pour les emails | Appels API Brevo |
| `MAIL_FROM` | obligatoire | Adresse expéditrice validée chez Brevo |
| `MAIL_FROM_NAME` | fallback | Nom d’expéditeur de repli ; le nom métier vient de `site_config` |

Le transport actif utilise l’API HTTP Brevo, pas un transport SMTP générique. Une erreur Brevo doit rester visible dans les logs et dans le flux métier concerné.

### Cloudinary

| Variable | Production | Rôle |
|---|---:|---|
| `CLOUDINARY_CLOUD_NAME` | recommandé | Stockage durable des images métier |
| `CLOUDINARY_API_KEY` | recommandé | Authentification Cloudinary |
| `CLOUDINARY_API_SECRET` | recommandé | Authentification Cloudinary |

Sans Cloudinary, les images utilisent le stockage local et ne doivent pas être supposées persistantes sur une plateforme à filesystem éphémère.

### Licence / entitlements

| Variable | Production | Rôle |
|---|---:|---|
| `TUGERES_ENTITLEMENTS_MODE` | `signed` pour une instance commerciale | Autorité de licence |
| `TUGERES_LICENSE_PUBLIC_KEY_B64` | obligatoire en mode `signed` | Clé publique PEM encodée en base64 |

La clé privée de signature ne doit jamais être déployée sur l’instance du traiteur.

## 3. Cycle de déploiement

Le `Dockerfile` construit l’image de production. `docker/entrypoint.sh` :

1. valide `PORT` et `MIGRATION_STARTUP_ATTEMPTS` ;
2. adapte Apache au port fourni par la plateforme ;
3. exécute `php bin/migrate.php` avant Apache ;
4. ne retente que les erreurs de disponibilité MySQL transitoires ;
5. refuse de démarrer Apache si une migration échoue de manière permanente ;
6. démarre Apache uniquement après succès du migrateur.

Les chemins HTTP ne doivent pas créer ni modifier le schéma. `FacturationModel::ensureSchema()` est conservé uniquement comme no-op de compatibilité.

### Déploiement Railway

Le dépôt utilise le builder Dockerfile. Après chaque déploiement, vérifier :

```text
GET /health  -> liveness applicative ; peut répondre 200 même si la DB est indisponible
GET /ready   -> readiness ; doit répondre 200 avec {"status":"ready"}
```

`/ready` vérifie la connexion MySQL et l’existence de `schema_migrations`. Pour un go-live, la readiness est le contrôle pertinent pour confirmer que l’instance est exploitable.

## 4. Migrations

Le migrateur suit chaque fichier dans `schema_migrations` avec un checksum SHA-256 et prend un verrou MySQL `GET_LOCK`.

Règles d’exploitation :

- ne jamais modifier une migration déjà appliquée ;
- ajouter une nouvelle migration numérotée pour toute évolution ;
- une dérive de checksum ou de schéma doit bloquer le démarrage ;
- ne jamais activer `TUGERES_ALLOW_HTTP_MIGRATIONS=true` en production normale ;
- le chemin manuel est `php bin/migrate.php` dans un environnement contrôlé.

La migration la plus récente attendue au moment de ce runbook est `045_facturation_pdf_path.sql`.

## 5. Stripe — checklist opérationnelle

Avant activation des paiements réels :

- utiliser les clés `live` uniquement sur l’instance commerciale ;
- configurer le webhook sur `/stripe/webhook` avec son propre `STRIPE_WEBHOOK_SECRET` ;
- vérifier qu’un checkout crée d’abord le brouillon de commande et la tentative de paiement durables ;
- tester un paiement réussi, un retour navigateur tardif, un webhook rejoué et un paiement échoué ;
- tester l’annulation métier d’une commande encaissée et vérifier l’écriture de remboursement dans le ledger ;
- ne jamais supprimer manuellement une écriture de paiement pour « corriger » un solde.

Les remboursements manuels externes restent une responsabilité opérateur lorsque le flux applicatif ne peut pas finaliser l’opération distante automatiquement.

## 6. Facturation et documents privés

Les devis, factures, avoirs et PDF finalisés sont servis via des contrôles applicatifs et stockés dans la zone privée de facturation. Les chemins historiques publics ne doivent pas être utilisés comme stockage durable.

Avant un pilote :

- finaliser un devis puis l’envoyer par email ;
- tester le lien de signature ;
- finaliser une facture et générer son PDF ;
- vérifier que le document reste accessible après redéploiement ;
- annuler une commande éligible et vérifier la création de l’avoir attendu ;
- vérifier les droits filesystem de `storage/facturation`.

La persistance après redéploiement est un **critère bloquant** pour considérer l’archivage opérationnel comme fiable.

## 7. Licence Tugères

### Génération hors instance client

La clé privée reste dans un environnement AkikSystems maîtrisé :

```bash
php bin/sign-license.php private-key.pem <license-id> <domain> <starter|pro|premium> [expires-at] > license.json
```

### Installation sur l’instance

Avec la clé publique configurée :

```bash
php bin/install-license.php license.json
```

Après installation vérifiée, passer `TUGERES_ENTITLEMENTS_MODE=signed` puis redéployer. Une licence invalide, expirée ou liée à un autre domaine doit échouer fermée en mode `signed`.

## 8. Cron de rappels

Le endpoint est :

```text
GET /cron/rappels
Header: X-Cron-Token: <secret>
```

Le secret attendu est la valeur `cron_secret_token` de `site_config`. Le traitement envoie les rappels J-7 et J-2 pour les commandes dans les statuts concernés et utilise des leases/idempotency records pour limiter les doublons.

Interprétation :

- HTTP 200 : aucune erreur d’envoi sur le lot ;
- HTTP 503 : au moins un rappel a échoué ; consulter les logs et relancer après correction.

Ne pas placer le token dans l’URL.

## 9. Rétention des données opérationnelles

Le nettoyage est volontairement en dry-run par défaut :

```bash
php bin/prune-operational-data.php
```

Valeurs par défaut :

- notifications lues : 365 jours ;
- logs de rappels envoyés : 180 jours ;
- lot : 500 lignes.

Application effective :

```bash
php bin/prune-operational-data.php --apply
```

Les options `--notification-days`, `--reminder-days` et `--batch-size` permettent d’ajuster les seuils dans les bornes imposées par l’application. Ce nettoyage ne constitue pas à lui seul une politique RGPD complète d’anonymisation des données métier.

## 10. Sauvegarde et restauration

Tugères n’embarque pas de moteur de backup automatisé. La sauvegarde est une responsabilité d’exploitation.

Avant toute migration importante ou changement de version, prendre une sauvegarde transactionnellement cohérente avec l’outil MySQL ou le mécanisme de snapshot du fournisseur. Exemple générique :

```bash
mysqldump --single-transaction --routines --triggers -h "$DB_HOST" -u "$DB_USER" -p "$DB_NAME" > backup.sql
```

Procédure de restauration générique :

1. arrêter ou isoler les écritures applicatives ;
2. restaurer la base dans une instance MySQL contrôlée ;
3. vérifier `schema_migrations` et les checksums ;
4. redéployer la version de code correspondant au backup ;
5. valider `/ready` ;
6. exécuter les smoke tests critiques avant réouverture.

La commande exacte et le mécanisme de snapshot dépendent du fournisseur MySQL utilisé. Ne pas documenter une restauration comme « testée » tant qu’un restore réel n’a pas été effectué.

## 11. Upgrade et rollback

### Upgrade

1. sauvegarde MySQL ;
2. vérifier le Quality Gate du commit cible ;
3. déployer l’image ;
4. laisser l’entrypoint appliquer les migrations ;
5. contrôler `/ready` ;
6. exécuter la checklist smoke ci-dessous ;
7. surveiller les logs applicatifs et les webhooks Stripe.

### Rollback

Les migrations n’ont pas de rollback SQL automatique. Si une migration destructive ou incompatible a été appliquée, revenir au code précédent peut être insuffisant. Le rollback sûr peut nécessiter la restauration du backup pris avant migration.

Ne jamais éditer une migration historique pour simuler un rollback.

## 12. Smoke tests de go-live

Un pilote professionnel n’est pas déclaré prêt tant que les points suivants ne sont pas validés sur l’environnement réellement exploité :

- [ ] déploiement du commit `main` attendu terminé sans erreur ;
- [ ] migrations appliquées jusqu’à la dernière version attendue ;
- [ ] `/health` répond ;
- [ ] `/ready` répond 200 ;
- [ ] inscription / connexion / reset password ;
- [ ] création d’une commande avec contrôle capacité/quota ;
- [ ] checkout Stripe de bout en bout ;
- [ ] webhook Stripe reçu et idempotent ;
- [ ] commande payée visible avec le bon montant ;
- [ ] stock et ledger cohérents ;
- [ ] devis finalisé, envoyé et signé ;
- [ ] facture/PDF généré et récupérable ;
- [ ] document privé encore présent après redéploiement ;
- [ ] annulation/remboursement métier cohérent ;
- [ ] cron rappels authentifié ;
- [ ] email Brevo reçu ;
- [ ] licence `signed` valide sur le domaine final ;
- [ ] sauvegarde MySQL disponible ;
- [ ] restauration testée sur une base de contrôle.

## 13. Go / no-go pilote

### Go

Le pilote peut ouvrir lorsque :

- CI du commit déployé est verte ;
- migrations et readiness sont vertes ;
- Stripe, email et documents privés passent les smoke tests ;
- la persistance des documents privés est démontrée ;
- un backup et un restore ont été testés ;
- l’instance utilise sa propre configuration et sa propre licence.

### No-go

Bloquer l’ouverture si l’un des points suivants est vrai :

- `/ready` échoue ;
- migration incomplète ou drift de checksum ;
- webhook Stripe non vérifié ;
- montant commande/paiement/facture incohérent ;
- documents privés perdus après redéploiement ;
- emails critiques non délivrés sans visibilité d’erreur ;
- absence de sauvegarde restaurable ;
- licence commerciale non valide.

## 14. Limites connues post-pilote

Ces sujets restent importants mais ne sont pas tous des blockers du premier pilote si les tests opérationnels ci-dessus sont verts :

- suite d’intégration MySQL/concurrence plus complète ;
- outbox email générique durable ;
- gestion Stripe plus exhaustive des événements de remboursement ;
- poursuite du découpage de `FacturationModel` ;
- migration progressive des calculs monétaires historiques basés sur `float` ;
- validation fine des proxies de confiance/CIDR ;
- politique RGPD complète au-delà de la purge opérationnelle actuelle.
