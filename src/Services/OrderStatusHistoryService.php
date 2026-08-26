<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\OrderStatus;
use PDO;
use RuntimeException;

final class OrderStatusHistoryService
{
    public static function append(
        PDO $db,
        int $commandeId,
        ?string $ancienStatut,
        string $nouveauStatut,
        ?string $commentaire,
        ?int $modifiePar,
    ): void {
        if (!$db->inTransaction()) {
            throw new RuntimeException('L’historique de statut doit être écrit dans une transaction.');
        }
        if ($commandeId <= 0 || !OrderStatus::isValid($nouveauStatut)) {
            throw new RuntimeException('Événement de statut invalide.');
        }

        if ($ancienStatut === null) {
            if ($nouveauStatut !== OrderStatus::initial()) {
                throw new RuntimeException('Le premier événement doit porter le statut initial.');
            }
        } else {
            if (!OrderStatus::isValid($ancienStatut) || $ancienStatut === $nouveauStatut) {
                throw new RuntimeException('Transition historique invalide.');
            }
            if (!OrderStatus::canTransition($ancienStatut, $nouveauStatut)) {
                throw new RuntimeException('Transition historique interdite.');
            }
        }

        $commentaire = $commentaire !== null ? trim($commentaire) : null;
        if ($commentaire === '') {
            $commentaire = null;
        }

        $stmt = $db->prepare(
            'INSERT INTO commande_historique
                (commande_id, ancien_statut, nouveau_statut, commentaire, modifie_par)
             VALUES (?, ?, ?, ?, ?)',
        );
        $stmt->execute([
            $commandeId,
            $ancienStatut,
            $nouveauStatut,
            $commentaire,
            $modifiePar,
        ]);
        $historiqueId = (int) $db->lastInsertId();

        $guard = $db->prepare(
            'INSERT INTO commande_historique_guard (
                historique_id,
                commande_id,
                ancien_statut_guard,
                nouveau_statut,
                commentaire_guard,
                modifie_par_guard,
                created_at
             )
             SELECT
                historique_id,
                commande_id,
                ancien_statut_guard,
                nouveau_statut,
                commentaire_guard,
                modifie_par_guard,
                created_at
             FROM commande_historique
             WHERE historique_id = ?',
        );
        $guard->execute([$historiqueId]);
        if ($guard->rowCount() !== 1) {
            throw new RuntimeException('Impossible de verrouiller l’événement de statut.');
        }
    }
}
