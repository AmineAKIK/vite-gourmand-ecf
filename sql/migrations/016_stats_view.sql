-- Migration 016 — Vue SQL de statistiques CA (remplace MongoDB)
-- La dépendance MongoDB pour des statistiques simples est supprimée.
-- Toutes les stats de chiffre d'affaires sont calculées directement en MySQL
-- via cette vue et des requêtes agrégées dans StatsService.
--
-- Avantages :
-- - Aucune synchronisation dual-source à gérer
-- - Données toujours cohérentes et en temps réel
-- - Filtrables facilement (date, menu, statut, ville)
-- - Extensible : la vue peut être enrichie sans toucher au code PHP

SET NAMES utf8mb4;

-- ============================================================
-- 1. Vue principale : CA par ligne de commande
--    Une ligne = un menu dans une commande éligible au CA
-- ============================================================

CREATE OR REPLACE VIEW v_ca_stats AS
SELECT
    cl.ligne_id,
    cl.commande_id,
    c.numero_commande,
    cl.menu_id,
    m.titre                                         AS menu_titre,
    cl.nombre_personne,

    -- Prix brut (avant remise) : snapshots si disponibles, sinon prix_par_personne × nb
    COALESCE(
        NULLIF(cl.prix_par_personne_snapshot, 0) * cl.nombre_personne,
        cl.prix_menu + cl.remise_appliquee
    )                                               AS prix_brut_menu,

    -- Remise appliquée sur cette ligne
    cl.remise_appliquee,

    -- Prix net menu (après remise)
    cl.prix_menu                                    AS prix_net_menu,

    -- Frais de livraison portés sur cette ligne (0 sauf sur la 1ère ligne)
    cl.prix_livraison,

    -- Total TTC de la ligne
    cl.prix_total_ligne,

    -- Taux TVA utilisé
    COALESCE(cl.taux_tva_snapshot, 10.00)           AS taux_tva,

    -- Total HT calculé depuis le TTC et le taux TVA snapshot
    ROUND(
        cl.prix_total_ligne / (1 + COALESCE(cl.taux_tva_snapshot, 10.00) / 100),
        2
    )                                               AS prix_total_ligne_ht,

    -- TVA correspondante
    ROUND(
        cl.prix_total_ligne - cl.prix_total_ligne / (1 + COALESCE(cl.taux_tva_snapshot, 10.00) / 100),
        2
    )                                               AS tva_ligne,

    -- Informations commande
    c.statut,
    c.date_commande,
    c.date_prestation,
    c.ville_livraison,
    c.code_postal_livraison,

    -- Date de comptabilisation = date d'acceptation si disponible, sinon date de commande
    COALESCE(ch_accept.date_acceptation, c.date_commande) AS date_comptabilisation,

    -- Informations client (pour exports comptables)
    u.prenom                                        AS client_prenom,
    u.nom                                           AS client_nom,
    u.email                                         AS client_email

FROM commande c
JOIN commande_ligne cl     ON cl.commande_id = c.commande_id
JOIN menu m                ON m.menu_id      = cl.menu_id
JOIN utilisateur u         ON u.utilisateur_id = c.utilisateur_id
LEFT JOIN (
    SELECT
        commande_id,
        MIN(created_at) AS date_acceptation
    FROM commande_historique
    WHERE nouveau_statut = 'accepte'
    GROUP BY commande_id
) ch_accept ON ch_accept.commande_id = c.commande_id
WHERE c.statut IN (
    'accepte',
    'en_preparation',
    'en_cours_livraison',
    'livre',
    'en_attente_materiel',
    'terminee'
);

-- ============================================================
-- 2. Vue synthèse CA par commande (pour dashboard et export)
--    Une ligne = une commande complète avec total et statut paiement
-- ============================================================

CREATE OR REPLACE VIEW v_ca_commandes AS
SELECT
    c.commande_id,
    c.numero_commande,
    c.statut,
    c.date_commande,
    c.date_prestation,
    c.ville_livraison,
    c.prix_total                                    AS total_ttc,

    -- HT global de la commande (calculé depuis les snapshots de taux TVA par ligne)
    SUM(ROUND(
        cl.prix_total_ligne / (1 + COALESCE(cl.taux_tva_snapshot, 10.00) / 100),
        2
    ))                                              AS total_ht,

    -- TVA totale
    SUM(ROUND(
        cl.prix_total_ligne - cl.prix_total_ligne / (1 + COALESCE(cl.taux_tva_snapshot, 10.00) / 100),
        2
    ))                                              AS total_tva,

    -- Nombre de menus
    COUNT(cl.ligne_id)                              AS nb_menus,

    -- Nombre total de personnes
    SUM(cl.nombre_personne)                         AS nb_personnes,

    -- Frais de livraison (somme de toutes les lignes, = valeur réelle car portée sur 1 seule)
    SUM(cl.prix_livraison)                          AS frais_livraison,

    -- Date de comptabilisation
    COALESCE(ch_accept.date_acceptation, c.date_commande) AS date_comptabilisation,

    -- Montant encaissé (depuis la table paiement)
    COALESCE(vpc.total_encaisse, 0.00)              AS montant_encaisse,

    -- Solde restant dû
    ROUND(c.prix_total - COALESCE(vpc.total_encaisse, 0.00), 2) AS solde_restant,

    -- Statut de paiement calculé
    CASE
        WHEN COALESCE(vpc.total_encaisse, 0.00) <= 0
            THEN 'non_paye'
        WHEN ROUND(c.prix_total - COALESCE(vpc.total_encaisse, 0.00), 2) <= 0
            THEN 'solde'
        ELSE 'acompte_verse'
    END                                             AS statut_paiement,

    -- Client
    u.prenom                                        AS client_prenom,
    u.nom                                           AS client_nom,
    u.email                                         AS client_email

FROM commande c
JOIN commande_ligne cl      ON cl.commande_id    = c.commande_id
JOIN utilisateur u          ON u.utilisateur_id  = c.utilisateur_id
LEFT JOIN (
    SELECT commande_id, MIN(created_at) AS date_acceptation
    FROM commande_historique
    WHERE nouveau_statut = 'accepte'
    GROUP BY commande_id
) ch_accept ON ch_accept.commande_id = c.commande_id
LEFT JOIN v_paiements_commande vpc ON vpc.commande_id = c.commande_id
WHERE c.statut IN (
    'accepte',
    'en_preparation',
    'en_cours_livraison',
    'livre',
    'en_attente_materiel',
    'terminee'
)
GROUP BY
    c.commande_id, c.numero_commande, c.statut,
    c.date_commande, c.date_prestation, c.ville_livraison,
    c.prix_total, ch_accept.date_acceptation,
    vpc.total_encaisse,
    u.prenom, u.nom, u.email;

-- ============================================================
-- 3. Vue CA mensuel (pour graphique de tendance)
-- ============================================================

CREATE OR REPLACE VIEW v_ca_mensuel AS
SELECT
    YEAR(date_comptabilisation)                     AS annee,
    MONTH(date_comptabilisation)                    AS mois,
    DATE_FORMAT(date_comptabilisation, '%Y-%m')     AS annee_mois,
    COUNT(DISTINCT commande_id)                     AS nb_commandes,
    SUM(total_ttc)                                  AS ca_ttc,
    SUM(total_ht)                                   AS ca_ht,
    SUM(total_tva)                                  AS tva_collectee,
    SUM(nb_personnes)                               AS nb_personnes,
    ROUND(SUM(total_ttc) / COUNT(DISTINCT commande_id), 2) AS panier_moyen_ttc
FROM v_ca_commandes
GROUP BY
    YEAR(date_comptabilisation),
    MONTH(date_comptabilisation),
    DATE_FORMAT(date_comptabilisation, '%Y-%m')
ORDER BY annee DESC, mois DESC;

-- ============================================================
-- 4. Vue CA par menu (pour classement et analyse)
-- ============================================================

CREATE OR REPLACE VIEW v_ca_par_menu AS
SELECT
    s.menu_id,
    s.menu_titre,
    COUNT(DISTINCT s.commande_id)           AS nb_commandes,
    SUM(s.nombre_personne)                  AS nb_personnes,
    SUM(s.prix_net_menu)                    AS ca_menu_ttc,
    SUM(ROUND(s.prix_net_menu / (1 + s.taux_tva / 100), 2)) AS ca_menu_ht,
    ROUND(AVG(s.prix_net_menu), 2)          AS prix_moyen_menu,
    ROUND(AVG(s.nombre_personne), 1)        AS nb_personnes_moyen
FROM v_ca_stats s
GROUP BY s.menu_id, s.menu_titre
ORDER BY ca_menu_ttc DESC;
