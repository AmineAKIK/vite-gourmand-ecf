# Migrations — Ordre d'exécution

Les migrations doivent être appliquées **dans l'ordre numérique strict**.
Chaque fichier est idempotent (`IF NOT EXISTS`, `INSERT IGNORE`, `CREATE OR REPLACE VIEW`)
sauf les `MODIFY COLUMN` qui peuvent être relancés sans effet s'ils aboutissent au même type.

## Phase 1 — Fondations financières (migrations 012–016)

Exécuter dans cet ordre :

```
012_finance_foundations.sql   — DECIMAL sur commande/commande_ligne, snapshots, correction livraison_km
013_entreprise_profile.sql    — Profil entreprise et paramètres comptables dans site_config
014_paiements.sql             — Tables paiement + mode_paiement, extension type_document, vue v_paiements_commande
015_taux_tva.sql              — Table taux_tva, FK sur document_facturation_ligne et commande_ligne
016_stats_view.sql            — Vues SQL v_ca_stats, v_ca_commandes, v_ca_mensuel, v_ca_par_menu
```

**Dépendance critique :** `016_stats_view.sql` utilise `v_paiements_commande` créée dans `014`.
`015_taux_tva.sql` ajoute une FK sur `document_facturation_ligne` — doit venir après `014` qui modifie
`document_facturation`.

## Commande d'application (depuis la racine du projet)

```bash
mysql -u vg -pvg vite_gourmand < sql/migrations/012_finance_foundations.sql
mysql -u vg -pvg vite_gourmand < sql/migrations/013_entreprise_profile.sql
mysql -u vg -pvg vite_gourmand < sql/migrations/014_paiements.sql
mysql -u vg -pvg vite_gourmand < sql/migrations/015_taux_tva.sql
mysql -u vg -pvg vite_gourmand < sql/migrations/016_stats_view.sql
```

## Après migration

- Remplir les paramètres entreprise dans **Admin → Paramètres → Informations entreprise**
  (SIRET, adresse, téléphone, IBAN) — champs désormais obligatoires pour finaliser une facture
- Vérifier le régime TVA configuré (assujetti / non assujetti)
- Vérifier les taux TVA dans la table `taux_tva` correspondent à la situation actuelle

## Rollback

Aucun rollback automatisé. En cas de problème, restaurer depuis une sauvegarde avant migration.
Les `MODIFY COLUMN DECIMAL` sur des données DOUBLE existantes sont sans perte de données
(DECIMAL est plus précis, les valeurs arrondies à 2 décimales sont conservées).
