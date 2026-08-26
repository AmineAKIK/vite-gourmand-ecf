<?php

namespace App\Models;

use App\Config\Database;
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
        $row = $stmt->fetch();
        return $row ?: [
            'commande_id' => $commandeId,
            'total_encaisse' => 0.00,
            'total_acomptes' => 0.00,
            'total_soldes' => 0.00,
            'total_paiements_uniques' => 0.00,
            'total_rembourse' => 0.00,
            'nb_paiements' => 0,
            'derniere_date_paiement' => null,
        ];
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
            $indexed[(int) $row['commande_id']] = $row;
        }
        return $indexed;
    }

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
}
