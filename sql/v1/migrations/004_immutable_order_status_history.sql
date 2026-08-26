-- V1 forward-only: make order status history a true append-only audit log.
-- Historical events must survive both actor account cleanup and any attempted
-- parent-order deletion. Actor privacy is handled by anonymizing the user row.

-- MySQL keeps constraint names reserved until an ALTER TABLE statement ends,
-- so dropping and recreating the same names must be done in separate statements.
ALTER TABLE commande_historique
    DROP FOREIGN KEY fk_commande_historique_commande,
    DROP FOREIGN KEY fk_commande_historique_user;

ALTER TABLE commande_historique
    ADD CONSTRAINT fk_commande_historique_commande
        FOREIGN KEY (commande_id) REFERENCES commande(commande_id) ON DELETE RESTRICT,
    ADD CONSTRAINT fk_commande_historique_user
        FOREIGN KEY (modifie_par) REFERENCES utilisateur(utilisateur_id) ON DELETE RESTRICT;

CREATE TRIGGER trg_commande_historique_no_update
BEFORE UPDATE ON commande_historique
FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'commande_historique is append-only';

CREATE TRIGGER trg_commande_historique_no_delete
BEFORE DELETE ON commande_historique
FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'commande_historique is append-only';
