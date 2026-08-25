# Tugères V1 — migrations forward-only

Ce dossier contient exclusivement les changements de schéma **postérieurs à la baseline V1**.

## Convention

- `001_v1_baseline.sql` reste dans `sql/v1/` et n'est jamais exécuté par `Migrator`.
- La première migration future est `002_nom_explicite.sql`.
- Un numéro ne peut apparaître qu'une seule fois.
- Un fichier appliqué ne peut jamais être modifié : son SHA-256 est enregistré dans `schema_migrations`.
- Une erreur SQL interrompt la migration et le démarrage. Il n'existe ni shim, ni tolérance de duplicate/missing DDL, ni réparation automatique.
- Les migrations HTTP restent interdites par défaut.

## Responsabilités

- **Provisioning initial** : baseline `sql/v1/001_v1_baseline.sql` via le Provisioner (PR D).
- **Évolution d'une base provisionnée** : ce dossier via `App\Config\Migrator`.

Les anciens fichiers `sql/migrations/001–045` sont de l'historique pré-release. Le migrateur V1 ne les lit plus. Leur suppression physique du dépôt pourra être faite lorsque le chemin de provisioning V1 sera entièrement validé, sans les remettre dans le runtime.
