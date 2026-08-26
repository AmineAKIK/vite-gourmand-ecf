#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Config/config.php';

use App\Config\Database;
use App\Services\InventoryLedgerService;

$db = Database::getConnection();
$db->beginTransaction();

try {
    $db->exec(
        "INSERT INTO ingredient (libelle, unite, prix_unitaire, seuil_alerte, actif)
         VALUES ('CI inventory invariant', 'kg', 1.0000, 0.100, 1)",
    );
    $ingredientId = (int) $db->lastInsertId();

    InventoryLedgerService::appendManualMovement($db, $ingredientId, 'entree', '1.000', 'CI', null);
    InventoryLedgerService::appendManualMovement($db, $ingredientId, 'sortie', '0.750', 'CI', null);

    $balance = $db->prepare(
        "SELECT COALESCE(SUM(CASE
            WHEN type_mouvement = 'entree' THEN quantite
            WHEN type_mouvement = 'sortie' THEN -quantite
            WHEN type_mouvement = 'ajustement' THEN quantite
            ELSE 0 END), 0)
         FROM mouvement_stock WHERE ingredient_id = ?",
    );
    $balance->execute([$ingredientId]);
    if ((string) $balance->fetchColumn() !== '0.250') {
        throw new RuntimeException('Solde de stock CI inattendu avant test de refus.');
    }

    $rejected = false;
    try {
        InventoryLedgerService::appendManualMovement($db, $ingredientId, 'sortie', '0.251', 'CI negative', null);
    } catch (RuntimeException $e) {
        $rejected = str_contains($e->getMessage(), 'Stock insuffisant');
    }
    if (!$rejected) {
        throw new RuntimeException('Une sortie rendant le stock négatif a été acceptée.');
    }

    $count = $db->prepare('SELECT COUNT(*) FROM mouvement_stock WHERE ingredient_id = ?');
    $count->execute([$ingredientId]);
    if ((int) $count->fetchColumn() !== 2) {
        throw new RuntimeException('Le refus de stock négatif a laissé un mouvement partiel.');
    }

    $adjustmentRejected = false;
    try {
        InventoryLedgerService::appendManualMovement($db, $ingredientId, 'ajustement', '0.100', 'CI ambiguous', null);
    } catch (InvalidArgumentException) {
        $adjustmentRejected = true;
    }
    if (!$adjustmentRejected) {
        throw new RuntimeException('Un nouvel ajustement ambigu a été accepté.');
    }

    $precisionRejected = false;
    try {
        InventoryLedgerService::appendManualMovement($db, $ingredientId, 'entree', '0.0001', 'CI precision', null);
    } catch (InvalidArgumentException) {
        $precisionRejected = true;
    }
    if (!$precisionRejected) {
        throw new RuntimeException('Une quantité dépassant trois décimales a été acceptée.');
    }

    $db->rollBack();
    fwrite(STDOUT, "Inventory invariants verified.\n");
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    fwrite(STDERR, 'Inventory invariant verification failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
