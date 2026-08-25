# Tugères V1 — contrat de baseline base de données

Ce répertoire porte le **schéma canonique des installations neuves Tugères V1**.

## Frontière de responsabilité

`001_v1_baseline.sql` décrit directement l’état structurel attendu par le code V1. Il ne rejoue aucune transition de développement et ne dépend d’aucun fichier `sql/migrations/001–045`.

Cette PR ajoute le contrat mais **ne bascule pas encore le runtime existant dessus**. La séparation est volontaire :

- **PR B** : inventaire + baseline canonique ;
- **PR C** : migrateur V1 forward-only et suppression des shims de compatibilité pré-release ;
- **PR D** : provisioner unique pour CLI/Docker/CI ;
- **PR E** : exécution réelle du fresh install MySQL 8.4 + deuxième passage/no-op en CI.

Ainsi, l’instance Railway existante n’est pas modifiée par PR B.

## Sources utilisées pour construire le baseline

Le baseline résulte du croisement de :

1. `sql/schema.sql` ;
2. l’état structurel final produit par les migrations pré-release `001–045` ;
3. les requêtes réellement exécutées par `src/Models`, `src/Services` et les vues statistiques ;
4. les invariants déjà présents dans les politiques de domaine.

Quand deux migrations historiques décrivent successivement la même colonne avec des contrats différents, le baseline ne reproduit pas ces étapes : il choisit directement le contrat final consommé par le runtime.

## Ce que le baseline contient

### Structure produit

- identité, rôles, authentification et rate limiting ;
- conteneurs de configuration/branding ;
- catalogue, plats, menus et allergènes ;
- commandes, lignes, historique et notifications ;
- recettes et ledger de stock ;
- devis/facturation et lignes de documents ;
- paiements, remboursements et tentatives PSP ;
- réservations atomiques d’admission ;
- journal idempotent de rappels ;
- vues financières actuellement consommées.

### Référentiels stables uniquement

Le baseline initialise seulement les identifiants qui font aujourd’hui partie d’un contrat stable du runtime :

- rôles `utilisateur`, `employe`, `administrateur` avec ids 1/2/3 ;
- catégories structurelles `Entrée`, `Plat principal`, `Dessert` ;
- les 14 allergènes du profil marché France/UE.

Ces seeds ne représentent pas un traiteur particulier.

## Ce que le baseline exclut volontairement

Aucune donnée spécifique à une instance n’est injectée :

- aucun nom de traiteur ;
- aucune ville ;
- aucun logo/image de marque ;
- aucun menu ou plat de démonstration ;
- aucun thème/régime commercial prédéfini ;
- aucun horaire ;
- aucun tarif de livraison ;
- aucune remise ;
- aucun délai commercial ;
- aucun taux de pénalité/acompte ;
- aucun moyen de paiement activé ;
- aucun plan SaaS ;
- aucune licence/entitlement propriétaire ;
- aucun compte administrateur.

La configuration commerciale et l’onboarding seront contractualisés dans la Phase 2. Les décisions de monétisation Tugères restent hors scope.

## Principes de schéma

- MySQL 8.4 est le moteur de référence V1 tant qu’un autre support n’est pas explicitement décidé et testé.
- Le baseline cible exclusivement une base vide.
- Il n’utilise ni `ALTER TABLE`, ni `IF NOT EXISTS`, ni réparation conditionnelle : une incohérence doit échouer franchement.
- Les relations connues sont déclarées par FK directement, au lieu d’être réparées par une migration ultérieure.
- Les index opérationnels connus sont présents dès la création.
- Les tables financières utilisent les types finaux déjà attendus par le runtime ; la refonte Money/Pricing ultérieure reste un chantier séparé.
- `site_config` reste volontairement vide : les fallbacks actuels seront remplacés par le registre typé et l’état `configuration_incomplete` de Phase 2.

## Dette pré-release identifiée et non transportée

L’inventaire a confirmé plusieurs classes de dette qui ne font pas partie du contrat V1 :

- ancien modèle `plat_allergene` / colonne texte `plat.allergenes` ;
- seeds Bordeaux et anciens menus de démonstration ;
- correctifs HTML de données de démonstration ;
- migrations qui réajoutent/modifient la même colonne à plusieurs étapes ;
- valeurs commerciales injectées par migration (livraison, remise, acompte, pénalités, etc.) ;
- plan/suspension SaaS et licences historiques ;
- réparations conditionnelles de schéma.

Git conserve cet historique ; une nouvelle installation V1 n’a pas à le rejouer.

## Validation attendue

PR B apporte des tests de contrat statiques. Le gate d’exécution MySQL complet arrive en PR E et devra prouver :

1. base totalement vide ;
2. application du baseline ;
3. inventaire tables/FK/index/référentiels ;
4. démarrage du runtime ;
5. second passage sans mutation ;
6. `/ready` exploitable quand la configuration minimale requise est satisfaite.

Aucune base Railway ne sera supprimée ou recréée avant que la chaîne V1 soit entièrement verte et qu’une autorisation destructive explicite soit donnée.
