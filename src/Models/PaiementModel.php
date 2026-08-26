<?php

namespace App\Models;

use App\Config\Database;
use App\Domain\Money;
use App\Services\PaymentLedgerService;
use Throwable;

class PaiementModel
{
    private static function db(): \PDO
    {
        return Database::getConnection();
    }

    public static function getByCommande(int $commandeId): array
    {
        $stmt = self::db()->prepare(
            "SELECT p.*,
                    CASE WHEN p.nature = 'remboursement' THEN -p.montant_cents ELSE p.montant_cents END AS montant_cents,
                    (CASE WHEN p.nature = 'remboursement' THEN -p.montant_cents ELSE p.montant_cents END) / 100.0 AS montant,
                    u.prenom, u.nom
             FROM paiement p
             LEFT JOIN utilisateur u ON u.utilisateur_id = p.cree_par
             WHERE p.commande_id = ?
             ORDER BY p.date_paiement ASC, p.paiement_id ASC"
        );
        $stmt->execute([$commandeId]);
        return $stmt->fetchAll();
    }

    public static function getSyntheseByCommande(int $commandeId): array
    {
        $stmt = self::db()->prepare('SELECT * FROM v_paiements_commande WHERE commande_id = ?');
        $stmt->execute([$commandeId]);
        $row = $stmt->fetch() ?: [
            'commande_id' => $commandeId,
            'total_encaisse_cents' => 0,
            'total_acomptes_cents' => 0,
            'total_soldes_cents' => 0,
            'total_paiements_uniques_cents' => 0,
            'total_rembourse_cents' => 0,
            'nb_paiements' => 0,
            'derniere_date_paiement' => null,
        ];

        return self::withPresentationAmounts($row);
    }

    public static function getSynthesesByCommandeIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = self::db()->prepare("SELECT * FROM v_paiements_commande WHERE commande_id IN ($placeholders)");
        $stmt->execute(array_values($ids));
        $indexed = [];
        foreach ($stmt->fetchAll() as $row) {
            $indexed[(int) $row['commande_id']] = self::withPresentationAmounts($row);
        }
        return $indexed;
    }

    public static function statutPaiement(int $totalEncaisseCents, int $prixTotalCents): string
    {
        if ($prixTotalCents <= 0) {
            return 'non_paye';
        }
        if ($totalEncaisseCents >= $prixTotalCents) {
            return 'solde';
        }
        if ($totalEncaisseCents > 0) {
            return 'acompte';
        }
        return 'non_paye';
    }

    public static function getModePaiements(): array
    {
        return self::db()
            ->query('SELECT * FROM mode_paiement WHERE actif = 1 ORDER BY libelle ASC')
            ->fetchAll();
    }

    public static function create(array $data, ?int $creePar = null): int
    {
        $db = self::db();
        $ownsTransaction = !$db->inTransaction();
        if ($ownsTransaction) {
            $db->beginTransaction();
        }

        try {
            $id = PaymentLedgerService::recordCollection($db, $data, $creePar);
            if ($ownsTransaction) {
                $db->commit();
            }
            return $id;
        } catch (Throwable $e) {
            if ($ownsTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public static function delete(int $paiementId, ?int $creePar = null): void
    {
        $db = self::db();
        $ownsTransaction = !$db->inTransaction();
        if ($ownsTransaction) {
            $db->beginTransaction();
        }

        try {
            PaymentLedgerService::reverseManualCollection($db, $paiementId, $creePar);
            if ($ownsTransaction) {
                $db->commit();
            }
        } catch (Throwable $e) {
            if ($ownsTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public static function getById(int $paiementId): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM paiement WHERE paiement_id = ?');
        $stmt->execute([$paiementId]);
        return $stmt->fetch() ?: null;
    }

    private static function withPresentationAmounts(array $row): array
    {
        $row['total_encaisse'] = Money::toDecimalString((int)($row['total_encaisse_cents'] ?? 0));
        $row['total_acomptes'] = Money::toDecimalString((int)($row['total_acomptes_cents'] ?? 0));
        $row['total_soldes'] = Money::toDecimalString((int)($row['total_soldes_cents'] ?? 0));
        $row['total_paiements_uniques'] = Money::toDecimalString((int)($row['total_paiements_uniques_cents'] ?? 0));
        $row['total_rembourse'] = Money::toDecimalString((int)($row['total_rembourse_cents'] ?? 0));

        return $row;
    }
}
