<?php

namespace App\Controllers;

use App\Config\Configuration;
use App\Config\Database;
use App\Services\ReminderLeaseService;
use App\Services\ReminderMailTransport;

class CronController
{
    private function authenticate(): void
    {
        $token = $_SERVER['HTTP_X_CRON_TOKEN'] ?? '';
        $configured = Configuration::get('operator.cron.token');
        $expected = is_string($configured) ? $configured : '';

        if (!is_string($token) || $expected === '' || !hash_equals($expected, $token)) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }
    }

    public function rappels(): void
    {
        $this->authenticate();

        header('Content-Type: application/json; charset=utf-8');

        $db      = Database::getConnection();
        $today   = date('Y-m-d');
        $in2days = date('Y-m-d', strtotime('+2 days'));
        $in7days = date('Y-m-d', strtotime('+7 days'));

        $stmt = $db->prepare("
            SELECT c.commande_id, c.numero_commande, c.date_prestation,
                   c.statut, c.utilisateur_id, u.email, u.prenom
            FROM commande c
            JOIN utilisateur u ON u.utilisateur_id = c.utilisateur_id
            WHERE c.date_prestation IN (?, ?)
              AND c.statut IN ('accepte', 'en_preparation')
        ");
        $stmt->execute([$in2days, $in7days]);
        $commandes = $stmt->fetchAll();

        $sent = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($commandes as $cmd) {
            if (!$cmd['email']) {
                $skipped++;
                continue;
            }

            $jours = (int) round(
                (strtotime($cmd['date_prestation']) - strtotime($today)) / 86400
            );
            $typeRappel = 'prestation_j' . $jours;
            $commandeId = (int) $cmd['commande_id'];
            $dateCible = (string) $cmd['date_prestation'];

            $leaseToken = ReminderLeaseService::claim($commandeId, $typeRappel, $dateCible);
            if ($leaseToken === null) {
                $skipped++;
                continue;
            }

            try {
                ReminderMailTransport::send($cmd['email'], $cmd['prenom'] ?? '', $cmd, $jours);
                ReminderLeaseService::markSent($commandeId, $typeRappel, $dateCible, $leaseToken);
                $sent++;
            } catch (\Throwable $error) {
                try {
                    ReminderLeaseService::markFailed(
                        $commandeId,
                        $typeRappel,
                        $dateCible,
                        $leaseToken,
                        $error
                    );
                } catch (\Throwable $leaseError) {
                    error_log(
                        '[cron] impossible de libérer le lease rappel commande_id=' . $commandeId
                        . ': ' . $leaseError->getMessage()
                    );
                }

                error_log(
                    '[cron] rappel commande_id=' . $commandeId . ' impossible: ' . $error->getMessage()
                );
                $failed++;
            }
        }

        http_response_code($failed === 0 ? 200 : 503);
        echo json_encode([
            'ok'      => $failed === 0,
            'checked' => count($commandes),
            'sent'    => $sent,
            'failed'  => $failed,
            'skipped' => $skipped,
            'ts'      => date('c'),
        ]);
    }
}
