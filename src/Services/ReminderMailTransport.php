<?php

namespace App\Services;

use App\Config\SiteConfig;
use RuntimeException;

final class ReminderMailTransport
{
    public static function send(string $email, string $prenom, array $commande, int $joursRestants): void
    {
        if (BREVO_API_KEY === '') {
            throw new RuntimeException('BREVO_API_KEY non configurée.');
        }

        $numero = (string) ($commande['numero_commande'] ?? '');
        $date = (string) ($commande['date_prestation'] ?? '');
        $dateLabel = $date !== '' ? date('d/m/Y', strtotime($date)) : '';
        $when = $joursRestants === 1 ? 'demain' : "dans {$joursRestants} jours";
        $siteName = SiteConfig::name();
        $safeName = htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8');
        $safePrenom = htmlspecialchars($prenom, ENT_QUOTES, 'UTF-8');
        $safeNumero = htmlspecialchars($numero, ENT_QUOTES, 'UTF-8');
        $safeDate = htmlspecialchars($dateLabel, ENT_QUOTES, 'UTF-8');
        $followUrl = BASE_URL . '/commande/suivi';

        $html = '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"></head><body>'
            . '<p>Bonjour ' . $safePrenom . ',</p>'
            . '<p>Votre prestation <strong>' . $safeNumero . '</strong> est prévue <strong>' . $when . '</strong>'
            . ($safeDate !== '' ? ' le <strong>' . $safeDate . '</strong>' : '') . '.</p>'
            . '<p>Vous pouvez consulter votre commande ici : <a href="'
            . htmlspecialchars($followUrl, ENT_QUOTES, 'UTF-8') . '">suivi de commande</a>.</p>'
            . '<p>À très bientôt,<br>L’équipe ' . $safeName . '</p></body></html>';

        $payload = [
            'sender' => ['name' => $siteName, 'email' => MAIL_FROM],
            'to' => [['email' => $email]],
            'subject' => 'Rappel : votre prestation ' . $when,
            'htmlContent' => $html,
            'textContent' => "Bonjour {$prenom}, votre prestation {$numero} est prévue {$when}"
                . ($dateLabel !== '' ? " le {$dateLabel}" : '') . ". Suivi : {$followUrl}",
        ];

        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $ch = curl_init('https://api.brevo.com/v3/smtp/email');
        if ($ch === false) {
            throw new RuntimeException('Impossible d’initialiser le transport Brevo.');
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $encoded,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'accept: application/json',
                'api-key: ' . BREVO_API_KEY,
                'content-type: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $errno !== 0) {
            throw new RuntimeException('Brevo indisponible : ' . ($error !== '' ? $error : 'erreur transport'));
        }

        if ($status < 200 || $status >= 300) {
            throw new RuntimeException('Brevo API ' . $status . ' : ' . mb_substr((string) $response, 0, 300));
        }
    }
}
