-- V1 forward-only: tenant payment method policy by journey.
-- Product-supported capability codes remain application invariants; this table stores
-- only tenant activation/rules. Fresh installs remain unconfigured until an admin
-- explicitly enables methods.

ALTER TABLE mode_paiement
    ADD COLUMN checkout_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER actif,
    ADD COLUMN manual_collection_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER checkout_enabled,
    ADD COLUMN allow_deposit TINYINT(1) NOT NULL DEFAULT 1 AFTER manual_collection_enabled,
    ADD COLUMN allow_balance TINYINT(1) NOT NULL DEFAULT 1 AFTER allow_deposit,
    ADD COLUMN allow_single_payment TINYINT(1) NOT NULL DEFAULT 1 AFTER allow_balance,
    ADD COLUMN instructions TEXT NULL AFTER allow_single_payment,
    ADD CONSTRAINT chk_mode_paiement_flags CHECK (
        actif IN (0, 1)
        AND checkout_enabled IN (0, 1)
        AND manual_collection_enabled IN (0, 1)
        AND allow_deposit IN (0, 1)
        AND allow_balance IN (0, 1)
        AND allow_single_payment IN (0, 1)
    );

-- Preserve the historical tenant activation intent. Online card remains checkout-only:
-- it must never be forged as a manual Stripe collection in the employee workspace.
UPDATE mode_paiement
SET checkout_enabled = actif,
    manual_collection_enabled = CASE WHEN code = 'cb_online' THEN 0 ELSE actif END,
    allow_deposit = CASE WHEN code = 'cb_online' THEN 0 ELSE 1 END,
    allow_balance = CASE WHEN code = 'cb_online' THEN 0 ELSE 1 END,
    allow_single_payment = 1;
