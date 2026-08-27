-- V1 migration 007 — immutable finalized billing documents
--
-- Finalized billing content becomes append-only through a canonical snapshot plus
-- a relational guard. No trigger/SUPER privilege is required. Existing finalized
-- documents are snapshotted in place; no business row is deleted or rewritten.
--
-- If an untracked partial attempt exists, only these derived snapshot/guard tables
-- are rebuilt. They are fully derivable from document_facturation and its lines
-- while the application is stopped during migration startup.

DROP TABLE IF EXISTS billing_document_snapshot_guard;
DROP TABLE IF EXISTS billing_document_finalized_snapshot;

CREATE TABLE billing_document_finalized_snapshot (
    document_id INT NOT NULL PRIMARY KEY,
    schema_version SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    snapshot_payload JSON NOT NULL,
    payload_hash BINARY(32)
        GENERATED ALWAYS AS (UNHEX(SHA2(CAST(snapshot_payload AS CHAR), 256))) STORED,
    finalized_at DATETIME NOT NULL,
    finalized_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uk_billing_document_snapshot_immutable UNIQUE (document_id, payload_hash),
    CONSTRAINT fk_billing_document_snapshot_document
        FOREIGN KEY (document_id) REFERENCES document_facturation(document_id) ON DELETE RESTRICT,
    CONSTRAINT fk_billing_document_snapshot_actor
        FOREIGN KEY (finalized_by) REFERENCES utilisateur(utilisateur_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE billing_document_snapshot_guard (
    document_id INT NOT NULL PRIMARY KEY,
    payload_hash BINARY(32) NOT NULL,
    CONSTRAINT fk_billing_document_snapshot_guard FOREIGN KEY (document_id, payload_hash)
        REFERENCES billing_document_finalized_snapshot(document_id, payload_hash)
        ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Snapshot every finalized historical document. Money is normalized to integer
-- cents inside the contractual snapshot even though the legacy working table still
-- stores DECIMAL values. Quote decision/signature/delivery fields stay outside this
-- immutable billing payload because they are workflow/operational state.
INSERT INTO billing_document_finalized_snapshot (
    document_id,
    schema_version,
    snapshot_payload,
    finalized_at,
    finalized_by
)
SELECT
    d.document_id,
    1,
    JSON_OBJECT(
        'schema_version', 1,
        'document', JSON_OBJECT(
            'id', d.document_id,
            'order_id', d.commande_id,
            'type', d.type_document,
            'status', d.statut,
            'number', d.numero_document,
            'issued_on', DATE_FORMAT(d.date_emission, '%Y-%m-%d'),
            'service_on', IF(d.date_prestation IS NULL, NULL, DATE_FORMAT(d.date_prestation, '%Y-%m-%d'))
        ),
        'client', JSON_OBJECT(
            'name', d.client_nom,
            'email', d.client_email,
            'phone', d.client_telephone,
            'address', d.client_adresse,
            'city', d.client_ville,
            'postal_code', d.client_code_postal,
            'siren', d.client_siren
        ),
        'delivery', JSON_OBJECT(
            'address', d.adresse_livraison,
            'city', d.ville_livraison,
            'postal_code', d.code_postal_livraison
        ),
        'tax', JSON_OBJECT(
            'operation_category', d.categorie_operation,
            'vat_on_debits', IF(d.option_tva_debits = 1, TRUE, FALSE)
        ),
        'business_snapshot', d.entreprise_snapshot,
        'content', JSON_OBJECT(
            'public_note', d.note_publique,
            'legal_notice', d.mention_legale
        ),
        'money', JSON_OBJECT(
            'currency', 'EUR',
            'total_ht_cents', CAST(ROUND(d.total_ht * 100) AS SIGNED),
            'total_vat_cents', CAST(ROUND(d.total_tva * 100) AS SIGNED),
            'total_ttc_cents', CAST(ROUND(d.total_ttc * 100) AS SIGNED),
            'deposit_paid_cents', CAST(ROUND(d.montant_acompte_verse * 100) AS SIGNED),
            'balance_due_cents', CAST(ROUND(d.solde_a_regler * 100) AS SIGNED)
        ),
        'links', JSON_OBJECT(
            'deposit_document_id', d.document_acompte_id,
            'source_document_id', d.source_document_id
        ),
        'finalization', JSON_OBJECT(
            'at', DATE_FORMAT(COALESCE(d.finalized_at, d.updated_at, d.created_at), '%Y-%m-%d %H:%i:%s'),
            'by', d.finalized_by
        ),
        'lines', COALESCE(
            (
                SELECT JSON_ARRAYAGG(JSON_OBJECT(
                    'id', l.ligne_document_id,
                    'designation', l.designation,
                    'quantity', CAST(l.quantite AS CHAR),
                    'unit_ht_cents', CAST(ROUND(l.prix_unitaire_ht * 100) AS SIGNED),
                    'unit_ttc_cents', CAST(ROUND(l.prix_unitaire_ttc * 100) AS SIGNED),
                    'vat_rate', CAST(l.taux_tva AS CHAR),
                    'vat_rate_id', l.taux_tva_id,
                    'total_ht_cents', CAST(ROUND(l.total_ht * 100) AS SIGNED),
                    'total_vat_cents', CAST(ROUND(l.total_tva * 100) AS SIGNED),
                    'total_ttc_cents', CAST(ROUND(l.total_ttc * 100) AS SIGNED),
                    'order', l.ordre
                ))
                FROM document_facturation_ligne l
                WHERE l.document_id = d.document_id
            ),
            JSON_ARRAY()
        )
    ),
    COALESCE(d.finalized_at, d.updated_at, d.created_at),
    d.finalized_by
FROM document_facturation d
WHERE d.statut = 'finalise';

-- The guard FK makes every snapshot payload immutable: changing or deleting the
-- payload changes/removes its generated hash and is rejected by the relationship.
INSERT INTO billing_document_snapshot_guard (document_id, payload_hash)
SELECT document_id, payload_hash
FROM billing_document_finalized_snapshot;
