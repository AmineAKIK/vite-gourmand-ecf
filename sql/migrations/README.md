# Migrations — Ordre d'exécution

Les migrations doivent être appliquées **dans l'ordre numérique strict**.

Le runtime Tugères suit désormais chaque migration dans `schema_migrations` avec son checksum SHA-256.
Une migration déjà appliquée ne doit donc **jamais être modifiée** : toute évolution supplémentaire doit être ajoutée dans un nouveau fichier numéroté.

## Contrat du migrateur

- un verrou MySQL `GET_LOCK` empêche deux instances d'appliquer les migrations simultanément ;
- une migration n'est enregistrée comme appliquée qu'après succès de tous ses statements ;
- un checksum absent sur une ancienne installation est initialisé à partir du fichier actuellement livré ;
- un checksum déjà enregistré qui ne correspond plus au fichier provoque un arrêt ;
- une table créée par une migration suivie mais absente de la base est considérée comme une dérive de schéma et provoque un arrêt ;
- seuls certains cas DDL réellement idempotents sont tolérés pour la compatibilité avec les anciennes installations partiellement migrées ;
- aucune erreur de migration critique n'est masquée : l'application ne doit pas continuer sur un schéma inconnu.

Les migrations existantes utilisent notamment `IF NOT EXISTS`, `INSERT IGNORE` et `CREATE OR REPLACE VIEW` lorsque cela est pertinent. Les migrations historiques ne doivent plus être éditées une fois suivies par checksum.

## Phase 1 — Fondations financières (migrations 012–016)

Exécuter dans cet ordre :

```text
012_finance_foundations.sql   — DECIMAL sur commande/commande_ligne, snapshots, correction livraison_km
013_entreprise_profile.sql    — Profil entreprise et paramètres comptables dans site_config
014_paiements.sql             — Tables paiement + mode_paiement, extension type_document, vue v_paiements_commande
015_taux_tva.sql              — Table taux_tva, FK sur document_facturation_ligne et commande_ligne
016_stats_view.sql            — Vues SQL v_ca_stats, v_ca_commandes, v_ca_mensuel, v_ca_par_menu
```

**Dépendance critique :** `016_stats_view.sql` utilise `v_paiements_commande` créée dans `014`.
`015_taux_tva.sql` ajoute une FK sur `document_facturation_ligne` — elle doit venir après `014` qui modifie `document_facturation`.

## Application manuelle

Depuis la racine du projet, une migration peut toujours être appliquée manuellement avec le client MySQL lorsque l'exploitation l'exige. Dans ce cas, il faut également préserver la cohérence de `schema_migrations` ; le chemin normal reste le migrateur applicatif tant que PR21 n'a pas déplacé cette étape dans le cycle de déploiement.

## Après migration

- remplir les paramètres entreprise dans **Admin → Paramètres → Informations entreprise** ;
- vérifier le régime TVA configuré ;
- vérifier les taux TVA actifs ;
- ne jamais corriger une migration déjà appliquée en éditant son fichier : créer une migration suivante.

## Rollback

Aucun rollback automatisé. En cas de problème, restaurer depuis une sauvegarde prise avant migration ou appliquer une migration corrective explicite. Une migration SQL historique déjà suivie par checksum ne doit pas être réécrite pour simuler un rollback.
