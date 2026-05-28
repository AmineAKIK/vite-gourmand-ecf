<?php

namespace App\Services;

use App\Config\SiteConfig;
use App\Core\Formatter;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class MailService
{
    // ----------------------------------------------------------------
    // Site context — single source, called once per email send
    // ----------------------------------------------------------------

    private static function ctx(): array
    {
        return [
            'name'    => SiteConfig::name(),
            'email'   => SiteConfig::email(),
            'phone'   => SiteConfig::phone(),
            'address' => SiteConfig::fullAddress(),
            'color'   => SiteConfig::color('couleur_principale'),
            'color2'  => SiteConfig::color('couleur_secondaire'),
            'fond'    => SiteConfig::color('couleur_fond'),
        ];
    }

    // ----------------------------------------------------------------
    // Layout wrapper
    // ----------------------------------------------------------------

    private static function wrap(string $titre, string $body, array $ctx): string
    {
        $name    = htmlspecialchars($ctx['name'],    ENT_QUOTES, 'UTF-8');
        $color   = htmlspecialchars($ctx['color'],   ENT_QUOTES, 'UTF-8');
        $fond    = htmlspecialchars($ctx['fond'],    ENT_QUOTES, 'UTF-8');
        $address = htmlspecialchars($ctx['address'], ENT_QUOTES, 'UTF-8');
        $email   = htmlspecialchars($ctx['email'],   ENT_QUOTES, 'UTF-8');

        return <<<HTML
        <!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"></head>
        <body style="margin:0;padding:0;background:#f4f4f4;font-family:Arial,sans-serif">
            <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f4;padding:32px 0">
                <tr><td align="center">
                    <table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:8px;overflow:hidden;max-width:100%">
                        <tr><td style="background:{$color};padding:24px 32px">
                            <h1 style="margin:0;color:#fff;font-size:20px">{$name}</h1>
                        </td></tr>
                        <tr><td style="padding:32px;color:#2C2C2C;font-size:15px;line-height:1.6">
                            <h2 style="color:{$color};margin-top:0">{$titre}</h2>
                            {$body}
                        </td></tr>
                        <tr><td style="background:{$fond};padding:16px 32px;font-size:12px;color:#5F6470;text-align:center">
                            {$name} · {$address} · {$email}
                        </td></tr>
                    </table>
                </td></tr>
            </table>
        </body></html>
        HTML;
    }

    // ----------------------------------------------------------------
    // Transport
    // ----------------------------------------------------------------

    private static function send(
        string $to,
        string $subject,
        string $html,
        string $text,
        ?string $replyTo = null,
        array $attachments = []
    ): void {
        $apiKey = BREVO_API_KEY;
        if (!$apiKey) {
            throw new RuntimeException('BREVO_API_KEY non configurée.');
        }

        $payload = [
            'sender'      => ['name' => SiteConfig::name(), 'email' => MAIL_FROM],
            'to'          => [['email' => $to]],
            'subject'     => $subject,
            'htmlContent' => $html,
            'textContent' => $text,
        ];
        if ($replyTo) {
            $payload['replyTo'] = ['email' => $replyTo];
        }
        if ($attachments) {
            $payload['attachment'] = $attachments;
        }

        $ch = curl_init('https://api.brevo.com/v3/smtp/email');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => [
                'accept: application/json',
                'api-key: ' . $apiKey,
                'content-type: application/json',
            ],
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new RuntimeException("Brevo API $httpCode : $response");
        }
    }

    // ----------------------------------------------------------------
    // Email bodies
    // ----------------------------------------------------------------

    private static function bodyWelcome(string $prenom, array $ctx): string
    {
        $name  = htmlspecialchars($ctx['name'],  ENT_QUOTES, 'UTF-8');
        $color = htmlspecialchars($ctx['color'], ENT_QUOTES, 'UTF-8');
        $url   = BASE_URL . '/menus';
        return "<p>Bienvenue chez <strong>{$name}</strong> ! Votre compte a été créé avec succès.</p>"
             . "<p>Vous pouvez dès maintenant découvrir nos menus et passer commande.</p>"
             . "<p><a href='{$url}' style='background:{$color};color:#fff;padding:10px 22px;"
             . "border-radius:6px;text-decoration:none;display:inline-block;margin-top:8px'>"
             . "Découvrir nos menus</a></p>";
    }

    private static function bodyPasswordReset(string $link, array $ctx): string
    {
        $color = htmlspecialchars($ctx['color'], ENT_QUOTES, 'UTF-8');
        return "<p>Vous avez demandé la réinitialisation de votre mot de passe. "
             . "Cliquez sur le bouton ci-dessous (lien valable <strong>1 heure</strong>) :</p>"
             . "<p style='text-align:center;margin:24px 0'>"
             . "<a href='{$link}' style='background:{$color};color:#fff;padding:12px 28px;"
             . "border-radius:6px;text-decoration:none;display:inline-block'>"
             . "Réinitialiser mon mot de passe</a></p>"
             . "<p style='color:#5F6470;font-size:13px'>Si vous n'avez pas fait cette demande, "
             . "ignorez cet email. Votre mot de passe ne sera pas modifié.</p>";
    }

    private static function bodyCommandeConfirmation(array $commande, array $panier, array $ctx): string
    {
        $color      = htmlspecialchars($ctx['color'], ENT_QUOTES, 'UTF-8');
        $fond       = htmlspecialchars($ctx['fond'],  ENT_QUOTES, 'UTF-8');
        $name       = htmlspecialchars($ctx['name'],  ENT_QUOTES, 'UTF-8');
        $livraison  = Formatter::price($commande['prix_livraison'] ?? 0);
        $total      = Formatter::price($commande['prix_total']     ?? 0);

        $lignesHtml = '';
        foreach ($panier as $item) {
            $prixLigne   = round((float)$item['prix_par_personne'] * (int)$item['nombre_personne'], 2);
            $titre       = htmlspecialchars($item['titre'], ENT_QUOTES, 'UTF-8');
            $lignesHtml .= "<tr>"
                         . "<td style='padding:4px 0;color:#5F6470'>{$titre} (" . (int)$item['nombre_personne'] . " pers.)</td>"
                         . "<td style='padding:4px 0;text-align:right'>" . Formatter::price($prixLigne) . "</td>"
                         . "</tr>";
        }

        $numero  = htmlspecialchars($commande['numero_commande'],    ENT_QUOTES, 'UTF-8');
        $date    = htmlspecialchars($commande['date_prestation'],     ENT_QUOTES, 'UTF-8');
        $heure   = htmlspecialchars($commande['heure_livraison'],     ENT_QUOTES, 'UTF-8');
        $adresse = htmlspecialchars(
            $commande['adresse_livraison'] . ', ' . $commande['ville_livraison'],
            ENT_QUOTES, 'UTF-8'
        );

        return "<p>Bonjour,</p>"
             . "<p>Nous avons bien reçu votre commande. Voici le récapitulatif :</p>"
             . "<table style='width:100%;border-collapse:collapse;margin:16px 0'>"
             . "<tr><td style='padding:6px 0;color:#5F6470'>Numéro</td><td style='padding:6px 0'><strong>{$numero}</strong></td></tr>"
             . "<tr><td style='padding:6px 0;color:#5F6470'>Date</td><td style='padding:6px 0'>{$date} à {$heure}</td></tr>"
             . "<tr style='background:{$fond}'><td style='padding:6px 8px;color:#5F6470'>Adresse</td><td style='padding:6px 8px'>{$adresse}</td></tr>"
             . "</table>"
             . "<table style='width:100%;border-collapse:collapse;margin:8px 0'>"
             . "<tr><th style='text-align:left;padding:4px 0;color:#5F6470;font-weight:normal'>Menus</th>"
             . "<th style='text-align:right;padding:4px 0;color:#5F6470;font-weight:normal'>Prix</th></tr>"
             . $lignesHtml
             . "</table>"
             . "<table style='width:100%;border-collapse:collapse;border-top:2px solid {$color};margin-top:8px'>"
             . "<tr><td style='padding:6px 0;color:#5F6470'>Livraison</td><td style='padding:6px 0;text-align:right'>{$livraison}</td></tr>"
             . "<tr style='font-size:1.1em'><td style='padding:10px 0'><strong>Total</strong></td>"
             . "<td style='padding:10px 0;text-align:right;color:{$color}'><strong>{$total}</strong></td></tr>"
             . "</table>"
             . "<p>Merci pour votre confiance !<br>L'équipe {$name}</p>";
    }

    private static function bodyCommandeTerminee(string $link, array $ctx): string
    {
        $color = htmlspecialchars($ctx['color'], ENT_QUOTES, 'UTF-8');
        $name  = htmlspecialchars($ctx['name'],  ENT_QUOTES, 'UTF-8');
        return "<p>Nous espérons que vous avez été pleinement satisfait de notre prestation.</p>"
             . "<p>Votre retour est précieux pour nous aider à nous améliorer. "
             . "Connectez-vous à votre espace client pour laisser votre avis :</p>"
             . "<p style='text-align:center;margin:24px 0'>"
             . "<a href='{$link}' style='background:{$color};color:#fff;padding:12px 28px;"
             . "border-radius:6px;text-decoration:none;display:inline-block'>Donner mon avis</a></p>"
             . "<p>Merci de nous faire confiance,<br>L'équipe {$name}</p>";
    }

    private static function bodyMaterielRelance(string $prenom, array $ctx): string
    {
        $name   = htmlspecialchars($ctx['name'],   ENT_QUOTES, 'UTF-8');
        $email  = htmlspecialchars($ctx['email'],  ENT_QUOTES, 'UTF-8');
        $phone  = $ctx['phone'] ? htmlspecialchars($ctx['phone'], ENT_QUOTES, 'UTF-8') : '';
        $color2 = htmlspecialchars($ctx['color2'], ENT_QUOTES, 'UTF-8');

        $phoneHtml = $phone ? "<br>📞 {$phone}" : '';
        return "<p>Bonjour {$prenom},</p>"
             . "<p>Votre prestation comprenait du matériel prêté par {$name}. "
             . "Vous disposez de <strong>10 jours ouvrés</strong> à compter de la livraison pour le restituer.</p>"
             . "<div style='background:#FFF6DA;border-left:4px solid {$color2};padding:12px 16px;margin:16px 0;border-radius:4px'>"
             . "⚠️ Passé ce délai, des frais de <strong>600 € TTC</strong> seront facturés conformément à nos CGV."
             . "</div>"
             . "<p>Pour organiser le retour, contactez-nous :<br>"
             . "📧 <a href='mailto:{$email}'>{$email}</a>{$phoneHtml}</p>"
             . "<p>L'équipe {$name}</p>";
    }

    private static function bodyEmployeCreation(string $email, array $ctx): string
    {
        $name     = htmlspecialchars($ctx['name'],  ENT_QUOTES, 'UTF-8');
        $color    = htmlspecialchars($ctx['color'], ENT_QUOTES, 'UTF-8');
        $fond     = htmlspecialchars($ctx['fond'],  ENT_QUOTES, 'UTF-8');
        $loginUrl = BASE_URL . '/connexion';
        $safeEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');

        return "<p>Bonjour,</p>"
             . "<p>Un compte employé a été créé pour vous sur la plateforme <strong>{$name}</strong>.</p>"
             . "<table style='width:100%;border-collapse:collapse;margin:16px 0'>"
             . "<tr style='background:{$fond}'><td style='padding:8px;color:#5F6470'>Identifiant</td>"
             . "<td style='padding:8px'><strong>{$safeEmail}</strong></td></tr>"
             . "<tr><td style='padding:8px;color:#5F6470'>Mot de passe</td>"
             . "<td style='padding:8px'>Communiqué directement par votre administrateur</td></tr>"
             . "</table>"
             . "<p style='text-align:center;margin:24px 0'>"
             . "<a href='{$loginUrl}' style='background:{$color};color:#fff;padding:12px 28px;"
             . "border-radius:6px;text-decoration:none;display:inline-block'>Se connecter</a></p>"
             . "<p>L'équipe {$name}</p>";
    }

    private static function bodyDocument(array $document, array $commande, string $typeLabel, string $numero, array $ctx): string
    {
        $color     = htmlspecialchars($ctx['color'], ENT_QUOTES, 'UTF-8');
        $name      = htmlspecialchars($ctx['name'],  ENT_QUOTES, 'UTF-8');
        $nomClient = htmlspecialchars($document['client_nom'] ?? '', ENT_QUOTES, 'UTF-8');
        $safeType  = htmlspecialchars($typeLabel,                    ENT_QUOTES, 'UTF-8');
        $safeNum   = htmlspecialchars($numero,                       ENT_QUOTES, 'UTF-8');
        $safeRef   = htmlspecialchars($commande['numero_commande'] ?? '', ENT_QUOTES, 'UTF-8');
        $total     = Formatter::price($document['total_ttc'] ?? 0);

        return "<p>Bonjour {$nomClient},</p>"
             . "<p>Veuillez trouver ci-joint votre <strong>{$safeType}</strong> "
             . "lié à la commande <strong>{$safeRef}</strong>.</p>"
             . "<table style='width:100%;border-collapse:collapse;margin:16px 0'>"
             . "<tr><td style='padding:6px 0;color:#5F6470'>Document</td>"
             . "<td style='padding:6px 0'><strong>{$safeNum}</strong></td></tr>"
             . "<tr><td style='padding:6px 0;color:#5F6470'>Total TTC</td>"
             . "<td style='padding:6px 0;color:{$color}'><strong>{$total}</strong></td></tr>"
             . "</table>"
             . "<p>Merci pour votre confiance,<br>L'équipe {$name}</p>";
    }

    // ----------------------------------------------------------------
    // Public API
    // ----------------------------------------------------------------

    public static function sendWelcome(string $email, string $prenom): void
    {
        try {
            $ctx = self::ctx();
            self::send(
                $email,
                'Bienvenue chez ' . $ctx['name'] . ' !',
                self::wrap("Bienvenue {$prenom} !", self::bodyWelcome($prenom, $ctx), $ctx),
                "Bonjour {$prenom}, bienvenue chez {$ctx['name']} ! Connectez-vous sur " . BASE_URL
            );
        } catch (Throwable $e) {
            error_log('Erreur mail bienvenue : ' . $e->getMessage());
        }
    }

    public static function sendPasswordReset(string $email, string $token): void
    {
        try {
            $ctx  = self::ctx();
            $link = BASE_URL . '/reinitialiser?token=' . $token;
            self::send(
                $email,
                'Réinitialisation de votre mot de passe',
                self::wrap('Réinitialisation du mot de passe', self::bodyPasswordReset($link, $ctx), $ctx),
                "Réinitialisez votre mot de passe (valable 1h) : {$link}"
            );
        } catch (Throwable $e) {
            error_log('Erreur mail reset : ' . $e->getMessage());
        }
    }

    public static function sendCommandeConfirmation(string $email, array $commande, array $panier): void
    {
        try {
            $ctx       = self::ctx();
            $titresCsv = implode(', ', array_column($panier, 'titre'));
            self::send(
                $email,
                'Confirmation de votre commande #' . $commande['numero_commande'],
                self::wrap('Votre commande est confirmée !', self::bodyCommandeConfirmation($commande, $panier, $ctx), $ctx),
                "Commande #{$commande['numero_commande']} confirmée — Menus : {$titresCsv} — Total : " . Formatter::price($commande['prix_total'] ?? 0)
            );
        } catch (Throwable $e) {
            error_log('Erreur mail commande : ' . $e->getMessage());
        }
    }

    public static function sendCommandeTerminee(string $email, int $commandeId): void
    {
        try {
            $ctx  = self::ctx();
            $link = BASE_URL . '/connexion?next=/mon-compte';
            self::send(
                $email,
                'Votre avis nous intéresse !',
                self::wrap('Votre prestation est terminée !', self::bodyCommandeTerminee($link, $ctx), $ctx),
                "Votre commande est terminée. Donnez votre avis sur {$link}"
            );
        } catch (Throwable $e) {
            error_log('Erreur mail terminée : ' . $e->getMessage());
        }
    }

    private static function documentAttachment(array $document, string $htmlAbsolutePath): array
    {
        $numero  = $document['numero_document'] ?: ('document-' . (int)$document['document_id']);
        $safeRef = preg_replace('/[^A-Z0-9_-]+/i', '-', $numero) ?: 'document';

        $pdfRelative = $document['pdf_path'] ?? null;
        $pdfAbsolute = $pdfRelative
            ? dirname(__DIR__, 2) . '/public/' . ltrim($pdfRelative, '/')
            : null;

        if ($pdfAbsolute && is_file($pdfAbsolute)) {
            return ['name' => $safeRef . '.pdf', 'content' => base64_encode(file_get_contents($pdfAbsolute))];
        }

        // Essaie de générer le PDF à la volée
        try {
            $relativePdf  = \App\Models\FacturationModel::generatePdf((int)$document['document_id']);
            $absolutePdf  = dirname(__DIR__, 2) . '/public/' . ltrim($relativePdf, '/');
            if (is_file($absolutePdf)) {
                return ['name' => $safeRef . '.pdf', 'content' => base64_encode(file_get_contents($absolutePdf))];
            }
        } catch (\Throwable) {
            // fallback HTML si DomPDF échoue
        }

        return ['name' => $safeRef . '.html', 'content' => base64_encode(file_get_contents($htmlAbsolutePath))];
    }

    public static function sendDocumentFacturation(array $document, array $commande, string $archiveAbsolutePath): void
    {
        $email = trim((string)($document['client_email'] ?? ''));
        if (!$email) {
            throw new InvalidArgumentException('Email client manquant.');
        }
        if (!is_file($archiveAbsolutePath)) {
            throw new RuntimeException('Archive du document introuvable.');
        }

        $typeLabel = ($document['type_document'] ?? '') === 'ticket' ? 'ticket de caisse' : 'facture';
        $numero    = $document['numero_document'] ?: ('document #' . (int)$document['document_id']);
        $ctx       = self::ctx();

        self::send(
            $email,
            ucfirst($typeLabel) . ' ' . $numero . ' — ' . $ctx['name'],
            self::wrap(ucfirst($typeLabel) . ' disponible', self::bodyDocument($document, $commande, $typeLabel, $numero, $ctx), $ctx),
            ucfirst($typeLabel) . " {$numero} — total " . Formatter::price($document['total_ttc'] ?? 0),
            null,
            [self::documentAttachment($document, $archiveAbsolutePath)]
        );
    }

    public static function sendMaterielRelance(string $email, string $prenom): void
    {
        try {
            $ctx = self::ctx();
            self::send(
                $email,
                'Retour de matériel — ' . $ctx['name'],
                self::wrap('Retour de matériel — Action requise', self::bodyMaterielRelance($prenom, $ctx), $ctx),
                "Bonjour {$prenom}, vous avez 10 jours ouvrés pour restituer le matériel prêté. "
                . "Passé ce délai : 600 € de frais. Contact : {$ctx['email']}"
            );
        } catch (Throwable $e) {
            error_log('Erreur mail matériel : ' . $e->getMessage());
        }
    }

    public static function sendEmployeCreation(string $email): void
    {
        try {
            $ctx = self::ctx();
            self::send(
                $email,
                'Votre compte employé ' . $ctx['name'],
                self::wrap('Votre compte employé a été créé', self::bodyEmployeCreation($email, $ctx), $ctx),
                "Votre compte employé a été créé. Identifiant : {$email}. "
                . "Mot de passe communiqué par l'administrateur. Connexion : " . BASE_URL . '/connexion'
            );
        } catch (Throwable $e) {
            error_log('Erreur mail employé : ' . $e->getMessage());
        }
    }

    public static function sendDevis(array $document, array $commande, string $archiveAbsolutePath): void
    {
        $email = trim((string)($document['client_email'] ?? ''));
        if (!$email) {
            throw new \InvalidArgumentException('Email client manquant.');
        }
        if (!is_file($archiveAbsolutePath)) {
            throw new \RuntimeException('Archive du devis introuvable.');
        }

        $numero = $document['numero_document'] ?: ('devis #' . (int)$document['document_id']);
        $ctx    = self::ctx();

        self::send(
            $email,
            'Votre devis ' . $numero . ' — ' . $ctx['name'],
            self::wrap('Votre devis est prêt', self::bodyDevis($document, $commande, $numero, $ctx), $ctx),
            "Bonjour, votre devis {$numero} est disponible en pièce jointe. "
            . "Total estimé : " . Formatter::price($document['total_ttc'] ?? 0)
            . ". Ce devis est valable 30 jours.",
            null,
            [self::documentAttachment($document, $archiveAbsolutePath)]
        );
    }

    public static function sendDevisSignatureRequest(array $document, string $signatureUrl): void
    {
        $email = trim((string)($document['client_email'] ?? ''));
        if (!$email) {
            throw new \InvalidArgumentException('Email client manquant.');
        }

        $ctx       = self::ctx();
        $numero    = $document['numero_document'] ?: ('devis #' . (int)$document['document_id']);
        $nomClient = htmlspecialchars($document['client_nom'] ?? '', ENT_QUOTES, 'UTF-8');
        $safeNum   = htmlspecialchars($numero, ENT_QUOTES, 'UTF-8');
        $name      = htmlspecialchars($ctx['name'],  ENT_QUOTES, 'UTF-8');
        $color     = htmlspecialchars($ctx['color'], ENT_QUOTES, 'UTF-8');
        $safeUrl   = htmlspecialchars($signatureUrl, ENT_QUOTES, 'UTF-8');
        $total     = Formatter::price($document['total_ttc'] ?? 0);

        $validite = '';
        if (!empty($document['date_emission'])) {
            $validite = date('d/m/Y', strtotime($document['date_emission'] . ' +30 days'));
        }

        $body = "<p>Bonjour {$nomClient},</p>"
              . "<p>Votre devis <strong>{$safeNum}</strong> d'un montant de <strong style='color:{$color}'>{$total}</strong>"
              . ($validite ? " est valable jusqu'au <strong>{$validite}</strong>" : '')
              . ".</p>"
              . "<p>Pour l'accepter électroniquement, cliquez sur le bouton ci-dessous :</p>"
              . "<p style='text-align:center;margin:28px 0'>"
              . "<a href='{$safeUrl}' style='background:{$color};color:#fff;padding:14px 32px;"
              . "border-radius:6px;text-decoration:none;font-weight:700;display:inline-block;font-size:15px'>"
              . "✍ Accepter ce devis</a></p>"
              . "<p style='font-size:12px;color:#6b7280'>Ce lien est à usage unique et sécurisé. "
              . "Si vous n'êtes pas à l'origine de cette demande, ignorez ce message.</p>"
              . "<p>Merci de votre confiance,<br>L'équipe {$name}</p>";

        self::send(
            $email,
            'Signature de votre devis ' . $numero,
            self::wrap('Acceptation de devis', $body, $ctx),
            "Bonjour {$document['client_nom']}, votre devis {$numero} attend votre acceptation : {$signatureUrl}"
        );
    }

    public static function sendRappelPrestation(string $email, string $prenom, array $commande, int $joursRestants): void
    {
        try {
            $ctx        = self::ctx();
            $numero     = $commande['numero_commande'] ?? '';
            $dateStr    = isset($commande['date_prestation'])
                ? date('d/m/Y', strtotime($commande['date_prestation']))
                : '';
            $safePrenom = htmlspecialchars($prenom,  ENT_QUOTES, 'UTF-8');
            $safeNum    = htmlspecialchars($numero,  ENT_QUOTES, 'UTF-8');
            $safeDate   = htmlspecialchars($dateStr, ENT_QUOTES, 'UTF-8');
            $name       = htmlspecialchars($ctx['name'],  ENT_QUOTES, 'UTF-8');
            $color      = htmlspecialchars($ctx['color'], ENT_QUOTES, 'UTF-8');
            $safeEmail  = htmlspecialchars($ctx['email'], ENT_QUOTES, 'UTF-8');

            $delaiLabel = $joursRestants === 1 ? 'demain' : "dans {$joursRestants} jours";
            $body = "<p>Bonjour {$safePrenom},</p>"
                  . "<p>Votre prestation <strong>{$safeNum}</strong> est prévue <strong>{$delaiLabel}</strong>"
                  . ($safeDate ? " le <strong>{$safeDate}</strong>" : '') . ".</p>"
                  . "<p>Tout est bien préparé de notre côté. Pour toute question de dernière minute, "
                  . "contactez-nous à <a href='mailto:{$safeEmail}'>{$safeEmail}</a>.</p>"
                  . "<p style='text-align:center;margin:24px 0'>"
                  . "<a href='" . htmlspecialchars(BASE_URL . '/commande/suivi', ENT_QUOTES, 'UTF-8') . "' "
                  . "style='background:{$color};color:#fff;padding:12px 28px;border-radius:6px;text-decoration:none;display:inline-block'>"
                  . "Voir ma commande</a></p>"
                  . "<p>À très bientôt,<br>L'équipe {$name}</p>";

            self::send(
                $email,
                'Rappel : votre prestation ' . ($joursRestants === 1 ? 'demain' : "dans {$joursRestants} jours"),
                self::wrap('Votre prestation approche', $body, $ctx),
                "Rappel : prestation {$numero} le {$dateStr} ({$delaiLabel})."
            );
        } catch (Throwable $e) {
            error_log('Erreur mail rappel prestation : ' . $e->getMessage());
        }
    }

    private static function bodyDevis(array $document, array $commande, string $numero, array $ctx): string
    {
        $color     = htmlspecialchars($ctx['color'], ENT_QUOTES, 'UTF-8');
        $name      = htmlspecialchars($ctx['name'],  ENT_QUOTES, 'UTF-8');
        $nomClient = htmlspecialchars($document['client_nom'] ?? '', ENT_QUOTES, 'UTF-8');
        $safeNum   = htmlspecialchars($numero, ENT_QUOTES, 'UTF-8');
        $safeRef   = htmlspecialchars($commande['numero_commande'] ?? '', ENT_QUOTES, 'UTF-8');
        $total     = Formatter::price($document['total_ttc'] ?? 0);
        $validite  = htmlspecialchars(
            isset($document['date_emission'])
                ? date('d/m/Y', strtotime($document['date_emission'] . ' +30 days'))
                : '',
            ENT_QUOTES, 'UTF-8'
        );

        return "<p>Bonjour {$nomClient},</p>"
             . "<p>Nous avons le plaisir de vous adresser notre devis relatif à votre demande de prestation"
             . ($safeRef ? " <strong>{$safeRef}</strong>" : '') . ".</p>"
             . "<table style='width:100%;border-collapse:collapse;margin:16px 0'>"
             . "<tr><td style='padding:6px 0;color:#5F6470'>Référence devis</td>"
             . "<td style='padding:6px 0'><strong>{$safeNum}</strong></td></tr>"
             . "<tr><td style='padding:6px 0;color:#5F6470'>Montant estimé TTC</td>"
             . "<td style='padding:6px 0;color:{$color}'><strong>{$total}</strong></td></tr>"
             . ($validite ? "<tr><td style='padding:6px 0;color:#5F6470'>Valable jusqu'au</td>"
             . "<td style='padding:6px 0'><strong>{$validite}</strong></td></tr>" : '')
             . "</table>"
             . "<p>Le devis détaillé est disponible en pièce jointe. Pour l'accepter ou poser "
             . "une question, répondez simplement à cet email ou contactez-nous à "
             . "<a href='mailto:{$ctx['email']}'>{$ctx['email']}</a>.</p>"
             . "<p>Merci de votre confiance,<br>L'équipe {$name}</p>";
    }

    public static function sendContact(string $titre, string $description, string $emailExp): void
    {
        try {
            $ctx      = self::ctx();
            $safeDesc = nl2br(htmlspecialchars($description, ENT_QUOTES, 'UTF-8'));
            $safeExp  = htmlspecialchars($emailExp, ENT_QUOTES, 'UTF-8');
            $safeTitre = htmlspecialchars($titre,   ENT_QUOTES, 'UTF-8');
            $body = "<p><strong>De :</strong> {$safeExp}</p>"
                  . "<p><strong>Sujet :</strong> {$safeTitre}</p>"
                  . "<hr style='border:none;border-top:1px solid #eee;margin:16px 0'>"
                  . "<p>{$safeDesc}</p>";
            self::send(
                MAIL_FROM,
                "[Contact] {$titre}",
                self::wrap('Nouveau message de contact', $body, $ctx),
                "Message de {$emailExp} — {$titre} : {$description}",
                $emailExp
            );
        } catch (Throwable $e) {
            error_log('Erreur mail contact : ' . $e->getMessage());
        }
    }
}
