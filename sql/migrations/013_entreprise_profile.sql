-- Migration 013 — Profil entreprise et paramètres comptables
-- Toutes les informations de l'entreprise sont stockées dans site_config
-- pour être modifiables par l'admin sans intervention développeur.
-- Le snapshot JSON de chaque document sera construit depuis ces valeurs au moment de la création.

SET NAMES utf8mb4;

-- ============================================================
-- 1. Informations légales de l'entreprise
--    Obligatoires sur les factures françaises (art. L441-9 C. com.)
-- ============================================================

INSERT IGNORE INTO site_config (cle, valeur) VALUES
    -- Identité
    ('entreprise_nom',          'Vite & Gourmand'),
    ('entreprise_siret',        ''),       -- SIRET 14 chiffres — obligatoire sur facture
    ('entreprise_forme_juridique', ''),    -- ex: SARL, EI, SAS, Auto-entrepreneur
    -- Adresse complète
    ('entreprise_adresse',      ''),
    ('entreprise_code_postal',  ''),
    ('entreprise_ville',        'Bordeaux'),
    -- Contact
    ('entreprise_telephone',    ''),
    ('entreprise_email',        ''),       -- si différent de MAIL_FROM
    -- TVA
    ('entreprise_tva_intracom', ''),       -- numéro TVA intracommunautaire (si assujetti B2B)
    -- Régime fiscal : 'assujetti' ou 'non_assujetti'
    -- - assujetti     : factures avec HT / TVA / TTC séparés
    -- - non_assujetti : mention "TVA non applicable art. 293B CGI", prix TTC uniquement
    ('regime_tva',              'assujetti');

-- ============================================================
-- 2. Coordonnées bancaires (affichées sur les factures pour virement)
-- ============================================================

INSERT IGNORE INTO site_config (cle, valeur) VALUES
    ('banque_iban',             ''),       -- ex: FR76 XXXX XXXX XXXX XXXX XXXX XXX
    ('banque_bic',              ''),       -- ex: BNPAFRPPXXX
    ('banque_nom_banque',       '');       -- ex: BNP Paribas

-- ============================================================
-- 3. Conditions de paiement et mentions légales
--    Le traiteur met à jour ces valeurs si la loi change.
-- ============================================================

INSERT IGNORE INTO site_config (cle, valeur) VALUES
    -- Délai de paiement légal (art. L441-10 C. com. : 30 jours par défaut)
    ('delai_paiement_jours',    '30'),
    -- Taux de pénalités de retard (légal : 3× taux directeur BCE en vigueur)
    -- Le traiteur met à jour ce taux 2x/an (jan + juil) ou laisse son comptable le faire.
    ('penalites_retard_taux',   '12.00'),  -- exemple : 12% l'an (3 × 4%)
    -- Indemnité forfaitaire de recouvrement (obligatoire B2B depuis 2013)
    ('indemnite_recouvrement',  '40.00'),  -- 40€ fixe légal

    -- Mentions légales par défaut des documents finalisés
    -- Ces textes remplacent le placeholder "brouillon" à la finalisation.
    ('mention_facture',
     'Paiement à réception de facture. Tout retard de paiement entraîne des pénalités au taux de [penalites_retard_taux]% l''an ainsi qu''une indemnité forfaitaire de recouvrement de [indemnite_recouvrement] € (art. L441-10 C. com.).'),
    ('mention_ticket',
     'Merci pour votre confiance. Document non soumis à escompte.'),
    ('mention_acompte',
     'Facture d''acompte. Cet acompte sera déduit de la facture définitive.');

-- ============================================================
-- 4. Paramètres d'acompte
-- ============================================================

INSERT IGNORE INTO site_config (cle, valeur) VALUES
    -- Taux d'acompte suggéré lors de la création d'une facture d'acompte (en %)
    ('acompte_taux_defaut',     '30');     -- 30% du total TTC

-- ============================================================
-- 5. Paramètre de réduction : clarifier le type de seuil
--    Résout l'ambiguïté entre REDUCTION_SEUIL=5 (personnes, mort) et
--    reduction_seuil=100 (montant €, actif) en formalisant le type.
-- ============================================================

INSERT IGNORE INTO site_config (cle, valeur) VALUES
    ('reduction_seuil_type',    'montant');  -- 'montant' (€) — la seule logique active
