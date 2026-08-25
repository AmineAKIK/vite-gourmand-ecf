# Migrations — contrat d’exploitation

Les migrations doivent être appliquées **dans l’ordre numérique strict**.

Le runtime Tugères suit chaque migration dans `schema_migrations` avec son checksum SHA-256. Une migration déjà appliquée ne doit donc **jamais être modifiée** : toute évolution supplémentaire doit être ajoutée dans un nouveau fichier numéroté.

## Contrat du migrateur

- un verrou MySQL `GET_LOCK` empêche deux instances d’appliquer les migrations simultanément ;
- une migration n’est enregistrée comme appliquée qu’après succès de tous ses statements ;
- un checksum absent sur une ancienne installation est initialisé à partir du fichier livré ;
- un checksum déjà enregistré qui ne correspond plus au fichier provoque un arrêt ;
- une table créée par une migration suivie mais absente de la base est considérée comme une dérive de schéma et provoque un arrêt ;
- seules les erreurs DDL explicitement prouvées idempotentes sont tolérées, de manière étroite ;
- les erreurs de migration critiques ne sont pas masquées.

Le migrateur ne tolère notamment pas de manière générique les erreurs « table already exists » ou « duplicate key/index ». Les exceptions de compatibilité restent limitées aux formes explicitement reconnues par `Migrator::isProvenIdempotentError()`.

## Cycle de déploiement

En production Docker, `docker/entrypoint.sh` exécute :

```bash
php bin/migrate.php
```

avant de démarrer Apache. Une erreur permanente de migration empêche le démarrage du serveur. Seules certaines erreurs de connexion MySQL transitoires déclenchent une nouvelle tentative, dans la limite de `MIGRATION_STARTUP_ATTEMPTS`.

Les requêtes HTTP ne doivent pas assurer le cycle de vie du schéma. Garder :

```text
TUGERES_ALLOW_HTTP_MIGRATIONS=false
```

`FacturationModel::ensureSchema()` est désormais un no-op de compatibilité ; la facturation dépend exclusivement des migrations pour son schéma.

## Historique financier important

Les migrations `012` à `016` ont posé les fondations financières : snapshots/DECIMAL, profil entreprise, paiements, TVA et vues statistiques.

Les migrations suivantes ont notamment ajouté :

- `036` : brouillons de commande et tentatives de paiement ;
- `037` : événements webhook Stripe ;
- `038` : réservations capacité/quota ;
- `039` : intégrité du ledger de stock ;
- `040` : intégrité paiements/remboursements ;
- `041` : état financier de facturation ;
- `042`–`043` : idempotence et leases des rappels ;
- `044` : index de rétention opérationnelle ;
- `045` : `pdf_path` dans le cycle de migrations de facturation.

## Application manuelle

Le chemin normal est le migrateur applicatif :

```bash
php bin/migrate.php
```

L’application manuelle d’un fichier SQL avec le client MySQL n’est réservée qu’à une opération d’exploitation maîtrisée, car elle peut désynchroniser `schema_migrations` et ses checksums. Si une intervention manuelle est indispensable, documenter précisément l’état avant/après et vérifier la cohérence du suivi des migrations avant de rouvrir le trafic.

## Après migration

- contrôler `/ready` ;
- vérifier les logs de démarrage ;
- vérifier que la dernière migration attendue est présente dans `schema_migrations` ;
- remplir/contrôler les paramètres entreprise dans **Admin → Paramètres** ;
- vérifier régime et taux de TVA ;
- exécuter les smoke tests critiques du runbook `docs/tugeres-operations.md`.

## Rollback

Il n’existe pas de rollback SQL automatisé. En cas de migration incompatible, revenir au code précédent peut être insuffisant. Le rollback sûr peut nécessiter la restauration d’une sauvegarde prise avant migration ou une migration corrective explicite.

Ne jamais réécrire une migration historique suivie par checksum pour simuler un rollback.
