<?php

namespace App\Controllers;

use App\Config\Database;
use App\Config\SiteConfig;
use App\Models\UserModel;
use App\Services\MailService;

class CronController
{
    private function authenticate(): void
    {
        $token = $_GET['token'] ?? $_SERVER['HTTP_X_CRON_TOKEN'] ?? '';
        $expected = SiteConfig::get('cron_secret_token', '');

        if ($expected === '' || !hash_equals($expected, $token)) {
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

        $sent  = 0;
        $skipped = 0;
        foreach ($commandes as $cmd) {
            if (!$cmd['email']) { $skipped++; continue; }
            $jours = (int)round(
                (strtotime($cmd['date_prestation']) - strtotime($today)) / 86400
            );
            MailService::sendRappelPrestation($cmd['email'], $cmd['prenom'] ?? '', $cmd, $jours);
            $sent++;
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
