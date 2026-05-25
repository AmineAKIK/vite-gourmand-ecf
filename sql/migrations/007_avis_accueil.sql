ALTER TABLE avis
    ADD COLUMN afficher_accueil TINYINT(1) NOT NULL DEFAULT 0 AFTER statut;

CREATE INDEX idx_avis_accueil ON avis (statut, afficher_accueil, created_at);
