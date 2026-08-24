<?php

namespace App\Controllers;

use App\Config\Database;
use App\Config\SiteConfig;
use App\Services\MailService;

class CronController
{
    private function authenticate(): void
    {
        $token = $_SERVER['HTTP_X_CRON_TOKEN'] ?? '';
        $expected = SiteConfig::get('cron_secret_token', '');

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
        foreach ($commandes as $cmd) {
            if (!$cmd['email']) {
                $skipped++;
                continue;
            }

            $jours = (int) round(
                (strtotime($cmd['date_prestation']) - strtotime($today)) / 86400
            );
            $typeRappel = 'prestation_j' . $jours;

            $reserved = $db->prepare(
                'INSERT IGNORE INTO cron_rappel_log (commande_id, type_rappel, date_cible) VALUES (?, ?, ?)'
            );
            $reserved->execute([(int) $cmd['commande_id'], $typeRappel, $cmd['date_prestation']]);
            if ($reserved->rowCount() !== 1) {
                $skipped++;
                continue;
            }

            try {
                MailService::sendRappelPrestation($cmd['email'], $cmd['prenom'] ?? '', $cmd, $jours);
                $db->prepare(
                    'UPDATE cron_rappel_log SET sent_at = NOW() WHERE commande_id = ? AND type_rappel = ? AND date_cible = ?'
                )->execute([(int) $cmd['commande_id'], $typeRappel, $cmd['date_prestation']]);
                $sent++;
            } catch (\Throwable $e) {
                $db->prepare(
                    'DELETE FROM cron_rappel_log WHERE commande_id = ? AND type_rappel = ? AND date_cible = ? AND sent_at IS NULL'
                )->execute([(int) $cmd['commande_id'], $typeRappel, $cmd['date_prestation']]);
                error_log('[cron] rappel commande_id=' . (int) $cmd['commande_id'] . ' impossible: ' . $e->getMessage());
                $skipped++;
            }
        }

        echo json_encode([
            'ok'      => true,
            'checked' => count($commandes),
            'sent'    => $sent,
            'skipped' => $skipped,
            'ts'      => date('c'),
        ]);
    }
}
