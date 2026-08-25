# Tugères V1 — Plan systémique de fondation produit

> Statut : proposition d’architecture avant travaux de reconstruction V1.
> Objet : définir **tout ce qui doit être stabilisé avant de considérer Tugères comme un produit white-label commercialisable**.
> Portée : produit traiteur complet de bout en bout. La stratégie commerciale de l’éditeur Tugères (abonnements, facturation SaaS, portail propriétaire, control plane) est volontairement **hors scope** pour l’instant, sauf interfaces nécessaires pour ne pas fermer les options futures.

---

## 1. Vision produit et règle fondamentale

Tugères V1 est un **moteur white-label pour traiteurs**. Une installation doit pouvoir représenter un traiteur différent sans modifier une seule ligne de code.

La règle architecturale principale est :

> Toute donnée, règle, apparence, intégration ou comportement spécifique à un traiteur doit être configurable au niveau approprié ; seules les contraintes intrinsèques au produit et à son intégrité restent codées comme invariants.

“100 % configurable” ne signifie pas que tout est modifiable par l’administrateur du traiteur. Chaque décision doit avoir un propriétaire clair :

- **invariant produit** : intégrité des montants, transitions d’état autorisées, contraintes de sécurité, règles DB ;
- **profil marché** : contraintes légales/géographiques d’un marché donné (V1 France) ;
- **configuration traiteur** : marque, contenus, catalogue, règles commerciales, opérations ;
- **configuration opérateur** : secrets, fournisseurs externes, infrastructure, stockage ;
- **contrôle éditeur futur** : droits commerciaux Tugères, laissé hors scope fonctionnel V1.

Une règle commerciale ne doit jamais exister en plusieurs exemplaires dans le code, les CGV, les emails et le calcul métier. Une seule source doit alimenter tous les usages.

---

## 2. Objectifs non négociables de la V1

À la fin du chantier, il doit être possible de déployer **deux instances A et B depuis le même commit et la même image Docker**, et d’obtenir deux traiteurs totalement différents uniquement par configuration et données.

Les deux instances doivent pouvoir différer sur :

- nom, domaine, logo, favicon, images et identité visuelle ;
- textes éditoriaux, SEO et contenus de page ;
- coordonnées, horaires et zones de service ;
- catalogue, menus, plats, allergènes, recettes et stocks ;
- tarification, remises et livraison ;
- délais de commande et capacité ;
- politique d’annulation ;
- acompte, solde, moyens de paiement disponibles ;
- politique de prêt/retour de matériel ;
- devis et durée de validité ;
- fiscalité et mentions de documents ;
- coordonnées bancaires ;
- règles de rappels et notifications ;
- expéditeur email et contenu métier ;
- documents PDF/archives ;
- fournisseurs techniques et stockage, sans changer le domaine métier.

La présence d’un réglage manquant critique doit produire un état **configuration_incomplete**, jamais un faux comportement commercial basé sur une valeur historique silencieuse.

---

## 3. Hors scope explicite pour cette reconstruction

Les sujets suivants seront volontairement reportés après stabilisation du produit :

- modèle de vente Tugères ;
- prix des abonnements Tugères ;
- plans Starter/Pro/Premium définitifs ;
- facturation du SaaS ;
- portail propriétaire multi-clients ;
- suspension commerciale centralisée ;
- provisioning automatisé multi-instance depuis un control plane ;
- télémétrie commerciale de l’éditeur ;
- mécanisme définitif de licence/entitlements.

Conséquence : la V1 produit ne doit **pas dépendre** d’un choix prématuré sur ces sujets. Les composants legacy actuels liés aux plans/licences/admin SaaS seront isolés ou retirés du chemin produit afin de ne pas contaminer l’architecture.

---

## 4. Principes d’architecture

### 4.1 Source unique par responsabilité

Chaque information possède une source canonique :

- schéma DB : baseline V1 ;
- config traiteur : registre typé ;
- secrets : environnement/opérateur ;
- montants : centimes entiers ;
- règles commerciales : objets de politique métier ;
- historique contractuel : snapshots immuables sur commandes/documents ;
- assets : abstraction de stockage durable ;
- emails : templates consommant les mêmes politiques métier ;
- statut commande : machine d’état unique.

### 4.2 Fail closed sur les données critiques

En production :

- DB absente → service non ready ;
- migration/baseline invalide → application non démarrée ;
- config commerciale obligatoire absente → checkout/commande bloqué explicitement ;
- secret Stripe manquant → paiement Stripe indisponible, jamais simulé ;
- stockage durable absent pour un artefact obligatoire → opération refusée ;
- incohérence financière → transaction refusée ;
- webhook non authentifié → rejet.

### 4.3 Pas de compatibilité pré-release conservée sans nécessité

Le produit n’ayant pas encore d’historique client à préserver, toute couche de compatibilité créée uniquement pour les itérations de développement doit être supprimée avant V1.

Git conserve l’histoire du développement ; le runtime n’a pas à la reproduire.

### 4.4 PR petites et réversibles

La reconstruction se fait par PR de responsabilité unique, dans l’ordre des dépendances. Une PR structurelle ne mélange pas nettoyage DB, UI et intégrations externes.

---

# PARTIE I — FONDATIONS TECHNIQUES

## 5. Base de données V1 canonique

### Problème actuel

Le projet applique un schéma relativement récent puis rejoue plusieurs dizaines de migrations pré-release qui reflètent des modèles successifs incompatibles. Le gate MySQL a démontré qu’une base neuve n’est pas reproductible.

### Cible

Créer une **baseline V1 unique et canonique** décrivant l’état exact attendu par le code V1.

### Travaux

1. Inventorier toutes les tables, colonnes, index, FK, CHECK, uniques et vues réellement consommés.
2. Identifier les tables/modèles morts ou hérités.
3. Générer un schéma V1 cohérent sans étapes intermédiaires historiques.
4. Séparer :
   - structure système ;
   - référentiels réglementaires nécessaires ;
   - données initiales neutres ;
   - données de démonstration (qui ne doivent pas être en production).
5. Supprimer du runtime les migrations pré-release `001–045` une fois le baseline validé.
6. Supprimer `LegacyAlterTableCompatibility` et réparations de migrations historiques devenues inutiles.
7. Redémarrer la numérotation post-V1 avec une convention claire (`001_v1_baseline.sql` puis `002_...`).
8. Maintenir `schema_migrations` strict pour les futures migrations forward-only.
9. Tester le baseline sur MySQL 8.4, cible V1 officielle.
10. Décider explicitement si MySQL 9.x est supporté ; ne pas revendiquer un support non nécessaire.

### Critères de sortie

- DB vide → baseline → succès ;
- second passage → no-op propre ;
- toutes contraintes attendues présentes ;
- aucune table legacy ;
- aucun seed spécifique à un traiteur ;
- aucun `IF NOT EXISTS` utilisé pour masquer une incohérence structurelle ;
- CI reproductible.

---

## 6. Modèle de configuration typé

### Objectif

Remplacer la logique “clé/valeur + fallback dispersé” par un **registre de configuration explicite**.

Chaque clé doit déclarer :

- identifiant ;
- scope ;
- type ;
- valeur par défaut autorisée ou absence de défaut ;
- validation ;
- caractère obligatoire ;
- caractère sensible ;
- rôle autorisé à modifier ;
- groupe UI ;
- description ;
- stratégie de migration future.

### Scopes proposés

- `system` : invariants techniques ;
- `market` : profil France V1 ;
- `tenant` : configuration du traiteur ;
- `operator` : infrastructure/secrets ;
- `future_entitlement` : réservé au futur contrôle commercial Tugères.

### Exemples

- `brand.name` → tenant/string/required ;
- `business.legal_name` → tenant/string/required avant facturation ;
- `quote.validity_days` → tenant/int ;
- `delivery.radius_km` → tenant/decimal ;
- `market.currency` → market = EUR ;
- `market.locale` → market = fr-FR ;
- `payment.stripe.enabled` → tenant/operator ;
- `STRIPE_SECRET_KEY` → operator/secret ;
- `storage.driver` → operator ;
- `order.number_prefix` → tenant ;
- `contact.response_sla_hours` → tenant ;
- `material.return_days` → tenant ;
- `material.late_fee_cents` → tenant.

### État d’onboarding

Ajouter un validateur global qui retourne :

- `ready` ;
- `configuration_incomplete` ;
- liste des clés critiques manquantes.

Le front public peut exister avant configuration complète, mais les parcours commerciaux sensibles doivent être bloqués jusqu’à satisfaction de leur contrat.

---

## 7. Gestion des secrets et environnement

### Cible

Une seule API `Environment` pour les variables processus. Aucun service ne relit directement `$_ENV`, `$_SERVER` ou `getenv()` de façon divergente.

### Travaux

- centraliser toutes les lectures ;
- interdire les secrets dans `site_config` ;
- documenter required/optional par environnement ;
- ajouter validation de démarrage pour secrets requis par feature activée ;
- supprimer les variables SMTP/Mongo historiques non utilisées ;
- ne jamais loguer les secrets ;
- fournir `.env.example` minimal, cohérent et sans valeurs trompeuses.

---

## 8. Bootstrap / installation / provisioning

### Problème

Le projet possède plusieurs chemins d’installation et un assistant historique qui applique directement schéma + migrations avec sa propre logique.

### Cible

**Un seul moteur de provisioning** utilisé par CLI, Docker, CI et éventuellement UI d’installation.

### Travaux

- définir un `Provisioner` canonique ;
- DB vide uniquement pour installation initiale ;
- appliquer baseline ;
- installer référentiels système ;
- créer le premier admin via un flux sûr ;
- valider la configuration minimale ;
- retirer toute duplication SQL dans `install/setup.php` ;
- supprimer secrets/licences legacy du setup ;
- produire un lock logique d’installation fiable ;
- installation idempotente sans masquer les erreurs.

---

# PARTIE II — MODÈLE MÉTIER

## 9. Identité entreprise et white-label

### Configuration nécessaire

- nom commercial ;
- raison sociale ;
- slogan ;
- domaine canonique ;
- email public ;
- téléphone ;
- adresse ;
- SIRET ;
- TVA intracom ;
- forme juridique ;
- coordonnées bancaires ;
- logo ;
- favicon ;
- OG image ;
- images éditoriales ;
- couleurs ;
- polices/thème ;
- attribution « Propulsé par Tugères » configurable contractuellement plus tard.

### Nettoyage

Supprimer des couches métier toute trace spécifique :

- Vite & Gourmand ;
- Bordeaux comme identité ;
- couleurs nommées `bordeaux/or/crème` dans les APIs ;
- `VG-` dans les références ;
- années d’expérience ;
- contenu commercial fictif ;
- coordonnées ou domaines historiques.

Les assets de fallback doivent être neutres et explicitement génériques.

---

## 10. CMS léger / contenu éditorial

Les textes commerciaux ne doivent pas être codés dans les vues.

### Blocs à rendre éditables

- hero ;
- présentation ;
- avantages/atouts ;
- CTA ;
- titre/description avis ;
- page contact ;
- délais de réponse affichés ;
- SEO title/description ;
- textes de footer ;
- CGV personnalisées ;
- mentions légales ;
- éventuellement FAQ.

### Contraintes

- contenu textuel échappé par défaut ;
- HTML riche uniquement via format contrôlé/sanitisé si ajouté ;
- aperçu admin ;
- defaults neutres et non mensongers.

---

## 11. Catalogue

### Entités

- catégories ;
- plats ;
- menus ;
- compositions ;
- thèmes ;
- régimes ;
- allergènes ;
- images ;
- disponibilité ;
- quantité/capacité éventuelle ;
- prix.

### Principes

- aucun menu exemple en production ;
- allergènes INCO comme référentiel système du profil France ;
- relations FK explicites ;
- contraintes de suppression cohérentes ;
- transactions atomiques lors de création/modification avec images ;
- intégrité catalogue testée.

---

## 12. Recettes et stocks

### Cible

Distinguer clairement :

- catalogue client ;
- recette interne ;
- ingrédient ;
- unité ;
- stock courant ;
- mouvement de stock ;
- consommation liée à une commande.

### À définir

- politique d’arrondi des quantités ;
- unités supportées ;
- stock négatif autorisé ou non ;
- transactions de consommation ;
- compensation si commande annulée ;
- audit des mouvements ;
- droits employés/admin.

---

## 13. Machine d’état des commandes

La machine d’état doit rester un **invariant produit centralisé**, sauf décision future contraire.

### À consolider

- états canoniques ;
- transitions autorisées ;
- actions déclenchées ;
- droits client/employé/admin ;
- effets sur CA ;
- effets sur stock ;
- effets sur paiements/remboursements ;
- effets sur avis ;
- effets sur matériel ;
- journal d’historique des changements.

Ajouter un `order_status_history` immuable si absent.

---

## 14. Capacité, disponibilité et délais de commande

Les valeurs doivent être des politiques configurables, pas du texte de CGV.

### Modèle proposé

- capacité max par jour ;
- capacité éventuellement par créneau ;
- jours fermés/exceptionnels ;
- délai minimum en fonction de la taille de commande ;
- cutoff horaire ;
- blackout dates ;
- politique de surcharge/refus.

Le même moteur doit servir : formulaire, validation serveur, back-office et CGV générées.

---

# PARTIE III — PRICING, LIVRAISON, PAIEMENT

## 15. Money / devise

### Invariant

Tous les montants transactionnels canoniques sont des **entiers en unité mineure** (centimes EUR V1).

### Travaux

- supprimer progressivement les calculs métier en float ;
- centraliser formatage monétaire ;
- snapshot de devise sur commande/document ;
- `EUR` comme propriété du profil marché V1, pas une chaîne répétée partout.

---

## 16. Politique de tarification

Créer une source unique pour :

- prix du catalogue ;
- quantité de personnes ;
- remises ;
- minimums ;
- frais livraison ;
- TVA ;
- acompte ;
- arrondis.

### Snapshot obligatoire

À la confirmation, stocker les valeurs ayant servi au calcul :

- prix unitaire ;
- quantité ;
- taux remise ;
- montant remise ;
- taux TVA ;
- frais livraison ;
- total ;
- devise ;
- version/identité de politique si utile.

Une modification future des paramètres ne doit jamais altérer une commande historique.

---

## 17. Livraison

### Décisions V1

- profil marché France ;
- validation adresse cohérente ;
- origine = coordonnées du traiteur ;
- rayon maximal configurable ;
- zones gratuites configurables ;
- tarification configurable.

### Point critique

Le texte contractuel doit correspondre à l’algorithme réel. Si le prix utilise distance à vol d’oiseau, les CGV ne doivent pas annoncer une distance routière.

### Architecture fournisseur

Créer une interface `GeocodingProvider` / éventuellement `RoutingProvider` afin que le domaine ne dépende pas directement d’API Adresse/Nominatim.

Le choix concret du fournisseur peut rester France V1.

---

## 18. Moyens de paiement

Ne pas coder « carte, virement, chèque » dans les CGV si ces moyens ne sont pas réellement activés.

### Modèle

Chaque moyen de paiement possède :

- identifiant ;
- activé/non activé ;
- disponibilité par parcours ;
- besoin de fournisseur externe ;
- règles d’acompte/solde ;
- instructions affichées ;
- impact sur statut commande.

Stripe est une implémentation de paiement CB, pas la politique métier elle-même.

---

## 19. Stripe

### Contrats V1

- secret serveur obligatoire si CB en ligne activée ;
- webhook secret obligatoire ;
- aucune confiance dans la success URL seule ;
- idempotency keys ;
- vérification montant/devise/commande ;
- webhook idempotent ;
- gestion des événements hors ordre ;
- tentative de paiement persistée ;
- réconciliation possible ;
- journal d’erreur sans secret.

### Tests

- succès ;
- abandon ;
- retry ;
- webhook doublé ;
- webhook avant retour navigateur ;
- mauvais montant ;
- mauvaise devise ;
- session expirée ;
- timeout provider.

---

## 20. Remboursements / annulations financières

Définir une politique métier canonique :

- annulation client autorisée jusqu’à quand ;
- taux retenu selon délai ;
- remboursement total/partiel ;
- avoir éventuel ;
- relation avec Stripe ;
- audit ;
- impossibilité de rembourser plus que payé.

La politique affichée dans les CGV doit être générée depuis la même source.

---

# PARTIE IV — DEVIS, FACTURATION, LÉGAL

## 21. Devis

Configurer :

- durée de validité ;
- acompte demandé ;
- template visuel ;
- conditions particulières ;
- workflow brouillon/finalisé/envoyé/accepté/refusé/expiré ;
- signature électronique si feature produit retenue.

La durée `+30 jours` ne doit pas être hardcodée dans la vue.

---

## 22. Facturation

### Invariants

- numérotation unique et monotone selon politique retenue ;
- document finalisé immuable ;
- corrections via avoir, pas mutation destructive ;
- snapshots client/entreprise/fiscalité ;
- montants cohérents en centimes ;
- PDF/archives reproductibles ;
- audit de finalisation.

### Configuration traiteur

- identité légale ;
- TVA ;
- IBAN/BIC ;
- mentions facture ;
- délais de paiement ;
- pénalités ;
- indemnité recouvrement ;
- préfixes de numérotation si juridiquement acceptable.

---

## 23. Stockage des documents

### Cible

Une interface `PrivateDocumentStorage` durable.

En production : aucun document comptable final ne doit dépendre du filesystem éphémère du conteneur.

### Exigences

- privé par défaut ;
- chemins non prédictibles ou accès contrôlé ;
- intégrité ;
- sauvegarde ;
- politique de rétention ;
- export administrateur ;
- test de persistance après redéploiement.

Le code de migration de chemins legacy doit disparaître avec le reset V1.

---

## 24. CGV, mentions légales, confidentialité

### Principe

Le logiciel ne doit pas fabriquer une promesse juridiquement fausse.

Deux approches possibles :

1. texte entièrement fourni/validé par le traiteur ;
2. template généré uniquement à partir de politiques réellement configurées.

### À rendre cohérent

- délais commande ;
- annulation ;
- remboursement ;
- moyens de paiement ;
- livraison ;
- matériel ;
- pénalités ;
- médiateur ;
- hébergeur ;
- durée conservation ;
- responsable traitement ;
- cookies/traceurs réellement utilisés.

L’hébergeur ne doit pas être hardcodé à Railway : c’est une propriété opérateur/déploiement.

---

# PARTIE V — COMMUNICATIONS ET AUTOMATISATIONS

## 25. Email

Créer une abstraction `MailTransport` et conserver les templates métier indépendants du fournisseur.

### Configuration

- sender email ;
- sender name ;
- reply-to ;
- fournisseur ;
- activation des types de mails.

### Templates à auditer

- bienvenue ;
- vérification email ;
- reset password ;
- confirmation commande ;
- changement statut ;
- devis ;
- facture ;
- rappel prestation ;
- retour matériel ;
- avis.

Aucune valeur commerciale (10 jours, 600 €, etc.) ne doit être codée directement dans les templates.

---

## 26. Rappels et cron

### Politique configurable

- rappels avant prestation : liste de délais (`J-7`, `J-2`, etc.) ;
- rappel retour matériel ;
- relances paiement si retenues ;
- activation par type.

### Runtime

- endpoint authentifié ;
- idempotence ;
- lease/verrou ;
- retry contrôlé ;
- historique d’envoi ;
- métriques ;
- cron planifié par l’infrastructure.

Les délais ne doivent pas être codés dans `CronController`.

---

## 27. Notifications internes

Définir :

- événements générateurs ;
- destinataires ;
- lu/non lu ;
- rétention ;
- purge ;
- pagination ;
- absence de données sensibles inutiles.

---

# PARTIE VI — UTILISATEURS ET SÉCURITÉ

## 28. Authentification

Auditer et tester :

- inscription ;
- vérification email ;
- connexion ;
- logout POST + CSRF ;
- reset password ;
- politique mot de passe ;
- changement mot de passe employé ;
- session fixation ;
- timeout session ;
- cookies Secure/HttpOnly/SameSite ;
- rate limiting ;
- messages non énumératifs.

---

## 29. Autorisation / rôles

V1 : utilisateur, employé, administrateur.

### À garantir

- aucune autorisation basée uniquement sur l’UI ;
- checks serveur systématiques ;
- contrôles d’appartenance des ressources ;
- exports protégés ;
- documents privés protégés ;
- endpoints admin/employee testés négativement.

Éviter de figer les IDs de rôle dans plusieurs couches si une représentation plus robuste est possible dans la baseline.

---

## 30. CSRF / XSS / CSP / headers

### Audit

- CSRF sur toutes mutations session-authenticated ;
- webhook exempt uniquement car authentifié par signature provider ;
- échappement par contexte HTML/attribute/URL/JS ;
- CSP cohérente avec dépendances ;
- supprimer headers obsolètes ;
- HSTS en production si HTTPS garanti ;
- frame ancestors ;
- MIME sniffing ;
- politique referrer.

---

## 31. Uploads et médias

### Exigences

- validation MIME réelle ;
- limites taille/dimensions ;
- noms générés ;
- jamais de code exécutable ;
- stockage durable en prod ;
- suppression transactionnelle/compensation ;
- suppression orphelins ;
- alt text / accessibilité selon besoin ;
- URLs externes validées.

---

## 32. Données personnelles / RGPD

### Inventaire

- compte client ;
- commandes ;
- adresses ;
- IP signature devis ;
- emails ;
- historiques ;
- notifications ;
- logs ;
- documents comptables.

### À définir

- finalité ;
- durée ;
- droit d’accès ;
- export ;
- anonymisation/suppression ;
- contraintes légales de conservation comptable ;
- distinction données supprimables vs documents à conserver.

Une politique de rétention opérationnelle doit être configurable sans permettre la destruction illégale des pièces comptables.

---

# PARTIE VII — UX WHITE-LABEL ET BACK-OFFICE

## 33. Design system sémantique

Remplacer les noms historiques par tokens :

- `brand-primary` ;
- `brand-secondary` ;
- `surface` ;
- `text` ;
- `success/warning/danger` ;
- typographies ;
- rayons/espacement si configurables.

Le CSS ne doit pas exposer l’identité d’un ancien traiteur.

---

## 34. Back-office de configuration

Structurer l’admin par domaines :

1. Identité ;
2. Entreprise & légal ;
3. Apparence & contenus ;
4. Catalogue ;
5. Livraison ;
6. Commandes & capacité ;
7. Tarification & remises ;
8. Paiements ;
9. Devis & facturation ;
10. Matériel ;
11. Notifications ;
12. Horaires ;
13. Intégrations visibles/non secrètes ;
14. Diagnostic configuration.

Chaque écran doit utiliser le registre typé, pas maintenir une seconde définition des validations.

---

## 35. UX d’onboarding

Après installation : checklist explicite avant mise en vente.

Exemple :

- identité complète ;
- coordonnées ;
- mentions légales ;
- horaires ;
- livraison ;
- TVA ;
- catalogue ;
- politique commerciale ;
- moyen de paiement ;
- email ;
- stockage ;
- test de commande.

Le système doit indiquer précisément pourquoi il n’est pas “ready for commerce”.

---

# PARTIE VIII — INTÉGRATIONS ET PORTABILITÉ

## 36. Ports/adapters

Introduire des interfaces là où le domaine dépend actuellement directement d’un fournisseur :

- `PaymentGateway` ;
- `MailTransport` ;
- `PublicMediaStorage` ;
- `PrivateDocumentStorage` ;
- `GeocodingProvider` ;
- éventuellement `RoutingProvider`.

Le but n’est pas de supporter dix fournisseurs en V1 ; le but est d’empêcher Stripe/Brevo/Cloudinary/Railway de devenir des règles métier.

---

## 37. Dépendances externes

Pour chaque fournisseur :

- timeout ;
- retry ;
- idempotence ;
- comportement en panne ;
- secret ;
- journalisation ;
- données envoyées ;
- conformité ;
- diagnostic readiness si fournisseur indispensable.

---

# PARTIE IX — RUNTIME / DÉPLOIEMENT / OPS

## 38. Image Docker

### Cible

- PHP/Apache version pinée ;
- extensions minimales ;
- Composer prod ;
- configtest build + runtime ;
- utilisateur/permissions maîtrisés ;
- pas de secrets dans image ;
- filesystem considéré éphémère ;
- reproductibilité.

---

## 39. Entrypoint

Séquence stricte :

1. valider runtime ;
2. attendre DB avec retry borné ;
3. appliquer migrations forward-only ;
4. valider config Apache ;
5. démarrer serveur.

Une erreur permanente doit arrêter le conteneur.

---

## 40. Health / readiness

- `/health` : processus vivant, sans dépendance lourde ;
- `/ready` : DB connectée, schéma attendu, config critique minimale, éventuellement stockage critique ;
- Railway doit utiliser `/ready` pour la mise en trafic.

Ne pas migrer dans une requête HTTP.

---

## 41. Stockage durable et backups

### Production

- DB avec stockage durable ;
- médias métier durables ;
- factures/archives durables ;
- sauvegarde DB ;
- procédure restauration ;
- test restauration réel avant go-live ;
- politique de rétention.

---

## 42. Logs et observabilité

### Logs structurés minimaux

- request correlation ID ;
- erreurs applicatives ;
- paiement ;
- migration ;
- mail ;
- cron ;
- stockage ;
- sécurité/rate limit.

### Interdits

- mots de passe ;
- clés API ;
- cookies/session IDs ;
- payload carte ;
- données personnelles excessives.

### Métriques utiles

- disponibilité ;
- erreurs 5xx ;
- latence ;
- checkout failures ;
- webhook failures ;
- emails failed ;
- cron failed ;
- readiness.

---

# PARTIE X — QUALITÉ ET CI

## 43. Quality Gate V1

La CI finale doit contenir au minimum :

### PHP

- syntaxe ;
- PHPUnit ;
- PHPStan ;
- Composer audit ;
- lint fichiers de config.

### DB

- MySQL 8.4 vide ;
- baseline ;
- migrations futures ;
- second passage idempotent ;
- vérification contraintes clés ;
- bootstrap admin/test fixture contrôlée.

### Container

- build Docker ;
- démarrage réel avec MySQL ;
- attente `/ready` ;
- requêtes smoke HTTP ;
- arrêt propre.

### White-label

Test automatique de deux configurations A/B pour vérifier absence de fuite de branding/données.

### Anti-hardcode

Ajouter un scan ciblé sur identifiants interdits pré-release :

- `Vite Gourmand` ;
- noms anciens ;
- domaines historiques ;
- préfixe `VG-` si remplacé ;
- valeurs commerciales connues retirées lorsqu’elles doivent être configurables.

Ce scan est un garde-fou, pas un remplacement de tests métier.

---

## 44. Tests métier critiques

### Commande

- création ;
- modification autorisée/interdite ;
- annulation ;
- capacité ;
- prix modifié entre panier et checkout ;
- transition statut ;
- stock.

### Livraison

- zone gratuite ;
- tarif normal ;
- hors rayon ;
- adresse invalide ;
- fournisseur indisponible.

### Finance

- TVA ;
- remise ;
- acompte ;
- paiement ;
- remboursement ;
- facture ;
- avoir ;
- invariants en centimes.

### Auth/sécurité

- CSRF ;
- ownership ;
- rôles ;
- reset ;
- rate limiting ;
- sessions.

### White-label

- identité A/B ;
- CGV A/B ;
- emails A/B ;
- PDF A/B ;
- pas de données croisées.

---

# PARTIE XI — NETTOYAGE DE DETTE PRÉ-RELEASE

## 45. Suppression explicite de legacy avant V1

Inventaire à supprimer/absorber selon résultat d’audit :

- migrations pré-release historiques ;
- `LegacyAlterTableCompatibility` ;
- réparations de migrations partielles ;
- compatibilités d’anciennes colonnes en euros si plus nécessaires après baseline ;
- fonctions `@deprecated` ;
- wrappers legacy qui n’ont plus d’appelants ;
- anciens scripts SQL complets parallèles ;
- anciens seeds spécifiques ;
- variables env inutilisées ;
- Mongo/SMTP historiques ;
- stockage public de facturation legacy ;
- mode licence legacy ;
- `/admin-saas` du chemin produit ;
- installateur SQL parallèle ;
- documentation qui décrit l’ancien fonctionnement.

Chaque suppression doit être précédée d’une recherche d’appelants et suivie par tests.

---

## 46. Documentation comme produit

Réécrire après stabilisation :

- README ;
- architecture ;
- installation ;
- configuration ;
- exploitation ;
- backup/restore ;
- sécurité ;
- modèle de données ;
- politique de migrations ;
- intégrations ;
- guide admin ;
- procédure release.

La documentation ne doit jamais contredire le runtime.

---

# PARTIE XII — SÉQUENCE DE PR RECOMMANDÉE

La numérotation ci-dessous est logique ; les numéros GitHub réels seront attribués au fil du travail.

## Phase 0 — Geler le contrat

### PR A — Constitution V1 (ce document)
- Aucun changement runtime.
- Valider scope, principes et ordre des travaux.

## Phase 1 — Fondations de données

### PR B — Inventaire de schéma et baseline V1
- baseline canonique ;
- référentiels système ;
- suppression des seeds historiques du chemin normal.

### PR C — Migrateur V1 forward-only
- retirer compatibilité pré-release ;
- migrateur strict minimal ;
- tests checksum/lock/idempotence.

### PR D — Provisioner unique
- CLI/Docker/CI utilisent le même chemin.

### PR E — Gate DB fresh install
- MySQL 8.4 base vide + deuxième démarrage.

## Phase 2 — Constitution de configuration

### PR F — Configuration registry typé
### PR G — Diagnostic `configuration_incomplete`
### PR H — Nettoyage Environment/secrets
### PR I — Refonte admin paramètres sur le registry

## Phase 3 — White-label réel

### PR J — Tokens de design sémantiques
### PR K — CMS léger homepage/contact/SEO
### PR L — Références, numérotation et assets neutres
### PR M — Test deux instances A/B + anti-hardcode

## Phase 4 — Politiques commerciales

### PR N — BusinessPolicy centrale
- délais, annulation, matériel, devis, rappels.

### PR O — Pricing/Money canonique
### PR P — DeliveryPolicy + provider adapter
### PR Q — Payment methods registry
### PR R — Génération CGV depuis politiques / contenu explicite

## Phase 5 — Catalogue et opérations

### PR S — Catalogue canonique
### PR T — Recettes/stocks invariants
### PR U — Capacité/disponibilité/calendrier
### PR V — Historique de statut commande/audit

## Phase 6 — Paiements

### PR W — PaymentGateway abstraction + Stripe
### PR X — Webhook/idempotence/réconciliation
### PR Y — Remboursements/annulations financières

## Phase 7 — Documents

### PR Z — Devis policy/workflow
### PR AA — Facturation V1 immuable
### PR AB — PrivateDocumentStorage durable
### PR AC — PDF/archives/snapshots

## Phase 8 — Communications

### PR AD — MailTransport + templates cohérents
### PR AE — Rappels configurables + scheduler contract
### PR AF — Notifications/rétention

## Phase 9 — Sécurité/RGPD

### PR AG — Audit auth/authorization
### PR AH — Headers/CSP/CSRF/upload hardening
### PR AI — RGPD/export/suppression/rétention

## Phase 10 — Runtime production

### PR AJ — `/health` vs `/ready`
### PR AK — Container smoke E2E en CI
### PR AL — stockage prod obligatoire et diagnostics
### PR AM — logs/observabilité

## Phase 11 — Suppression finale de dette

### PR AN — retirer compatibilité/deprecated morts
### PR AO — retirer composants SaaS/entitlements legacy du produit
### PR AP — documentation V1 complète
### PR AQ — release candidate gate

---

# PARTIE XIII — STRATÉGIE DE RESET DE L’INSTANCE ACTUELLE

Aucune suppression de base Railway ne doit avoir lieu pendant la construction du nouveau baseline.

### Avant reset

- baseline V1 vert en CI ;
- tests métier verts ;
- container smoke vert ;
- configuration V1 prête ;
- inventaire des données existantes ;
- confirmation qu’aucune donnée client réelle n’est à préserver ;
- sauvegarde/snapshot si possible ;
- procédure restauration définie.

### Opération destructive

Elle doit faire l’objet d’une autorisation explicite immédiatement avant exécution.

### Après reset

- créer DB V1 propre ;
- provisioning ;
- config traiteur ;
- admin ;
- `/ready` 200 ;
- smoke catalogue ;
- smoke commande ;
- smoke paiement test ;
- smoke email ;
- smoke document ;
- redéploiement et vérification persistance.

---

# PARTIE XIV — DÉFINITION DE DONE V1

Tugères V1 est considérée prête uniquement si toutes les conditions suivantes sont vraies.

## Architecture

- aucune dette de migration pré-release active ;
- baseline reproductible ;
- aucune logique legacy nécessaire au fonctionnement ;
- config typée et centralisée ;
- fournisseurs externes découplés du domaine.

## White-label

- aucune identité de traiteur dans le code métier ;
- deux instances A/B complètement différentes depuis le même commit ;
- aucune fuite de branding entre instances ;
- pas de texte commercial mensonger par défaut.

## Commerce

- prix calculés en centimes ;
- snapshots contractuels ;
- livraison cohérente ;
- politiques commerciales configurables ;
- paiement idempotent ;
- remboursement borné ;
- documents immuables.

## Sécurité

- auth/roles/ownership testés ;
- CSRF ;
- XSS/CSP ;
- secrets propres ;
- uploads durcis ;
- logs sans secrets ;
- rate limiting.

## Opérations

- Docker reproductible ;
- migrations avant serveur ;
- `/health` et `/ready` corrects ;
- stockage durable ;
- backup/restore testé ;
- cron fiable ;
- observabilité minimale.

## Qualité

- PHPUnit/PHPStan/Composer audit verts ;
- MySQL fresh install vert ;
- container smoke vert ;
- tests métier critiques verts ;
- anti-hardcode white-label vert ;
- documentation synchronisée.

---

# PARTIE XV — RÈGLES DE CONDUITE DU CHANTIER

1. Ne jamais faire passer un test en affaiblissant l’invariant qu’il protège.
2. Ne jamais modifier une règle commerciale seulement dans une vue ou un email.
3. Ne jamais ajouter un fallback silencieux pour contourner une configuration obligatoire.
4. Ne pas mélanger nettoyage pré-release et nouvelle feature dans la même PR.
5. Toute donnée historique contractuelle doit être snapshotée.
6. Toute opération externe retryable doit être idempotente ou protégée par une clé d’idempotence.
7. Les erreurs de dépendances externes ne doivent pas corrompre les transactions locales.
8. Une PR n’est mergée que sur son head exact après Quality Gate vert.
9. Après chaque merge runtime, vérifier le déploiement réel et les logs.
10. Toute opération destructive sur la DB ou les fichiers persistants exige une autorisation explicite immédiate.
11. L’architecture future de monétisation Tugères ne doit pas être inventée maintenant ; le produit doit simplement ne pas l’empêcher.
12. La V1 ne transporte aucune dette pré-release par défaut : une exception doit être justifiée par un besoin réel, documenté et testé.

---

## Conclusion

Le chantier n’est pas une suite de correctifs ponctuels. Il s’agit de transformer le dépôt actuel en **fondation V1 propre**, avec une frontière claire entre domaine traiteur, configuration white-label, infrastructure et futures responsabilités de l’éditeur Tugères.

La première implémentation après validation de ce plan doit commencer par la **base de données V1 canonique**, car toutes les autres couches dépendent du modèle de données et du contrat de configuration qui sera construit dessus.
