<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use App\Domain\OrderStatus;
use PDO;
use RuntimeException;
use Throwable;

final class OrderTransitionService
{
    /**
     * @return array{commande_id:int, ancien_statut:string, nouveau_statut:string, changed:bool}
     */
    public static function transition(
        int $commandeId,
        string $nouveauStatut,
        ?string $commentaire,
        ?int $modifiePar,
    ): array {
        return self::apply($commandeId, $nouveauStatut, $commentaire, $modifiePar, null, null);
    }

    /**
     * @return array{commande_id:int, ancien_statut:string, nouveau_statut:string, changed:bool}
     */
    public static function cancel(
        int $commandeId,
        string $motif,
        string $modeContact,
        ?int $modifiePar,
    ): array {
        $motif = trim($motif);
        $modeContact = trim($modeContact);
        if ($motif === '' || $modeContact === '') {
            throw new RuntimeException('Le motif et le mode de contact sont obligatoires pour une annulation.');
        }

        return self::apply(
            $commandeId,
            OrderStatus::cancelled(),
            sprintf('Annulation (%s) : %s', $modeContact, $motif),
            $modifiePar,
            $motif,
            $modeContact,
        );
    }

    /**
     * @return array{commande_id:int, ancien_statut:string, nouveau_statut:string, changed:bool}
     */
    private static function apply(
        int $commandeId,
        string $nouveauStatut,
        ?string $commentaire,
        ?int $modifiePar,
        ?string $motifAnnulation,
        ?string $modeContactAnnulation,
    ): array {
        if ($commandeId <= 0 || !OrderStatus::isValid($nouveauStatut)) {
            throw new RuntimeException('Transition de commande invalide.');
        }

        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            $commande = self::lockCommande($db, $commandeId);
            $ancienStatut = (string) ($commande['statut'] ?? '');

            if (!OrderStatus::isValid($ancienStatut)) {
                throw new RuntimeException('Le statut actuel de la commande est invalide.');
            }
            if (!OrderStatus::canTransition($ancienStatut, $nouveauStatut)) {
                throw new RuntimeException(sprintf(
                    'Transition interdite : %s → %s.',
                    OrderStatus::label($ancienStatut),
                    OrderStatus::label($nouveauStatut),
                ));
            }

            if ($ancienStatut === $nouveauStatut) {
                $db->commit();

                return [
                    'commande_id' => $commandeId,
                    'ancien_statut' => $ancienStatut,
                    'nouveau_statut' => $nouveauStatut,
                    'changed' => false,
                ];
            }

            if ($nouveauStatut === OrderStatus::cancelled()) {
                if ($motifAnnulation === null || $modeContactAnnulation === null) {
                    throw new RuntimeException('Une annulation doit passer par le workflow dédié.');
                }
                $stmt = $db->prepare(
                    'UPDATE commande
                     SET statut = ?, motif_annulation = ?, mode_contact_annulation = ?
                     WHERE commande_id = ?',
                );
                $stmt->execute([$nouveauStatut, $motifAnnulation, $modeContactAnnulation, $commandeId]);
            } else {
                $stmt = $db->prepare('UPDATE commande SET statut = ? WHERE commande_id = ?');
                $stmt->execute([$nouveauStatut, $commandeId]);
            }

            $history = $db->prepare(
                'INSERT INTO commande_historique
                    (commande_id, ancien_statut, nouveau_statut, commentaire, modifie_par)
                 VALUES (?, ?, ?, ?, ?)',
            );
            $history->execute([
                $commandeId,
                $ancienStatut,
                $nouveauStatut,
                $commentaire !== null && trim($commentaire) !== '' ? trim($commentaire) : null,
                $modifiePar,
            ]);

            $db->commit();

            return [
                'commande_id' => $commandeId,
                'ancien_statut' => $ancienStatut,
                'nouveau_statut' => $nouveauStatut,
                'changed' => true,
            ];
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    private static function lockCommande(PDO $db, int $commandeId): array
    {
        $stmt = $db->prepare('SELECT commande_id, statut FROM commande WHERE commande_id = ? FOR UPDATE');
        $stmt->execute([$commandeId]);
        $commande = $stmt->fetch();
        if (!$commande) {
            throw new RuntimeException('Commande introuvable.');
        }

        return $commande;
    }
}
