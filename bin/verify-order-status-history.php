#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Config/config.php';

use App\Config\Database;
use App\Domain\OrderStatus;
use App\Services\OrderStatusHistoryService;

$db = Database::getConnection();
$db->beginTransaction();

try {
    $user = $db->prepare(
        'INSERT INTO utilisateur (email, password, prenom, nom, role_id, actif, email_verified_at)
         VALUES (?, ?, ?, ?, ?, 1, NOW())',
    );
    $user->execute(['ci-order-owner@example.invalid', '*', 'CI', 'Owner', 1]);
    $ownerId = (int) $db->lastInsertId();
    $user->execute(['ci-order-actor@example.invalid', '*', 'CI', 'Actor', 2]);
    $actorId = (int) $db->lastInsertId();

    $order = $db->prepare(
        'INSERT INTO commande (
            numero_commande, utilisateur_id, date_prestation, heure_livraison,
            adresse_livraison, ville_livraison, code_postal_livraison,
            prix_total_cents, currency, payment_method_code, instructions
         ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
    );
    $order->execute([
        'CI-HISTORY-001',
        $ownerId,
        '2099-01-01',
        '12:00',
        '1 rue CI',
        'Paris',
        '75001',
        1000,
        'EUR',
        null,
        null,
    ]);
    $commandeId = (int) $db->lastInsertId();

    OrderStatusHistoryService::append(
        $db,
        $commandeId,
        null,
        OrderStatus::initial(),
        'CI initial event',
        $actorId,
    );

    $db->prepare('UPDATE commande SET statut = ? WHERE commande_id = ?')
        ->execute([OrderStatus::accepted(), $commandeId]);
    OrderStatusHistoryService::append(
        $db,
        $commandeId,
        OrderStatus::initial(),
        OrderStatus::accepted(),
        'CI accepted event',
        $actorId,
    );

    $count = $db->prepare('SELECT COUNT(*) FROM commande_historique WHERE commande_id = ?');
    $count->execute([$commandeId]);
    if ((int) $count->fetchColumn() !== 2) {
        throw new RuntimeException('Historique CI incomplet.');
    }

    $updateBlocked = false;
    try {
        $db->prepare('UPDATE commande_historique SET commentaire = ? WHERE commande_id = ?')
            ->execute(['tampered', $commandeId]);
    } catch (PDOException) {
        $updateBlocked = true;
    }
    if (!$updateBlocked) {
        throw new RuntimeException('UPDATE de l’historique autorisé alors qu’il doit être immuable.');
    }

    $deleteBlocked = false;
    try {
        $db->prepare('DELETE FROM commande_historique WHERE commande_id = ?')->execute([$commandeId]);
    } catch (PDOException) {
        $deleteBlocked = true;
    }
    if (!$deleteBlocked) {
        throw new RuntimeException('DELETE de l’historique autorisé alors qu’il doit être immuable.');
    }

    $orderDeleteBlocked = false;
    try {
        $db->prepare('DELETE FROM commande WHERE commande_id = ?')->execute([$commandeId]);
    } catch (PDOException) {
        $orderDeleteBlocked = true;
    }
    if (!$orderDeleteBlocked) {
        throw new RuntimeException('Suppression parent de commande autorisée malgré son historique.');
    }

    $actorDeleteBlocked = false;
    try {
        $db->prepare('DELETE FROM utilisateur WHERE utilisateur_id = ?')->execute([$actorId]);
    } catch (PDOException) {
        $actorDeleteBlocked = true;
    }
    if (!$actorDeleteBlocked) {
        throw new RuntimeException('Suppression de l’acteur autorisée malgré son historique.');
    }

    $db->rollBack();
    fwrite(STDOUT, "Order status history invariants verified.\n");
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    fwrite(STDERR, 'Order status history verification failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
