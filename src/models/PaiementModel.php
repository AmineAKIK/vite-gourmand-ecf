<?php
// src/models/PaiementModel.php

class PaiementModel
{
    // ----------------------------------------------------------------
    // Lecture
    // ----------------------------------------------------------------

    public static function getByCommande(int $commandeId): array
    {
        return db()->fetchAll(
            "SELECT p.*, u.prenom, u.nom
             FROM paiement p
             LEFT JOIN utilisateur u ON u.utilisateur_id = p.cree_par
             WHERE p.commande_id = ?
             ORDER BY p.date_paiement ASC, p.paiement_id ASC",
            [$commandeId]
        );
    }

    public static function getSyntheseByCommande(int $commandeId): array
    {
        $row = db()->fetchOne(
            "SELECT * FROM v_paiements_commande WHERE commande_id = ?",
            [$commandeId]
        );
        return $row ?: [
            'commande_id'             => $commandeId,
            'total_encaisse'          => 0.00,
            'total_acomptes'          => 0.00,
            'total_soldes'            => 0.00,
            'total_paiements_uniques' => 0.00,
            'nb_paiements'            => 0,
            'derniere_date_paiement'  => null,
        ];
    }

    /**
     * Returns syntheses keyed by commande_id for multiple commandes at once.
     */
    public static function getSynthesesByCommandeIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = db()->fetchAll(
            "SELECT * FROM v_paiements_commande WHERE commande_id IN ($placeholders)",
            array_values($ids)
        );
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[(int)$row['commande_id']] = $row;
        }
        return $indexed;
    }

    /**
     * Statut paiement : 'solde' | 'acompte' | 'non_paye'
     * Compares total_encaisse against prix_total (TTC de la commande).
     */
    public static function statutPaiement(float $totalEncaisse, float $prixTotal): string
    {
        if ($prixTotal <= 0) {
            return 'non_paye';
        }
        if ($totalEncaisse >= $prixTotal - 0.01) {
            return 'solde';
        }
        if ($totalEncaisse > 0) {
            return 'acompte';
        }
        return 'non_paye';
    }

    public static function getModePaiements(): array
    {
        return db()->fetchAll(
            "SELECT * FROM mode_paiement WHERE actif = 1 ORDER BY libelle ASC"
        );
    }

    // ----------------------------------------------------------------
    // Écriture
    // ----------------------------------------------------------------

    public static function create(array $data, ?int $creePar = null): int
    {
        $commandeId   = (int)($data['commande_id'] ?? 0);
        $typePaiement = $data['type_paiement'] ?? '';
        $montant      = round((float)($data['montant'] ?? 0), 2);
        $mode         = trim($data['mode'] ?? '');
        $date         = trim($data['date_paiement'] ?? '');
        $reference    = trim($data['reference'] ?? '') ?: null;
        $note         = trim($data['note'] ?? '') ?: null;
        $documentId   = !empty($data['document_id']) ? (int)$data['document_id'] : null;

        if (!$commandeId || !$typePaiement || $montant <= 0 || !$mode || !$date) {
            throw new InvalidArgumentException('Champs paiement obligatoires manquants.');
        }
        if (!in_array($typePaiement, ['acompte', 'solde', 'paiement_unique'], true)) {
            throw new InvalidArgumentException('Type de paiement invalide.');
        }
        $dateObj = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$dateObj) {
            throw new InvalidArgumentException('Date de paiement invalide.');
        }

        db()->execute(
            "INSERT INTO paiement
                (commande_id, document_id, type_paiement, montant, mode, date_paiement, reference, note, cree_par)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$commandeId, $documentId, $typePaiement, $montant, $mode, $date, $reference, $note, $creePar]
        );
        return (int)db()->lastInsertId();
    }

    public static function delete(int $paiementId): void
    {
        db()->execute(
            "DELETE FROM paiement WHERE paiement_id = ?",
            [$paiementId]
        );
    }

    public static function getById(int $paiementId): ?array
    {
        return db()->fetchOne(
            "SELECT * FROM paiement WHERE paiement_id = ?",
            [$paiementId]
        ) ?: null;
    }
}
