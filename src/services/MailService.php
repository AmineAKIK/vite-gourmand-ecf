<?php
// src/services/MailService.php

class MailService {

    private static function wrap(string $titre, string $body): string {
        $name    = htmlspecialchars(siteName(), ENT_QUOTES, 'UTF-8');
        $color   = htmlspecialchars(siteColor('couleur_principale'), ENT_QUOTES, 'UTF-8');
        $fond    = htmlspecialchars(siteColor('couleur_fond'), ENT_QUOTES, 'UTF-8');
        $adresse = htmlspecialchars(siteFullAddress(), ENT_QUOTES, 'UTF-8');
        $email   = htmlspecialchars(siteEmail(), ENT_QUOTES, 'UTF-8');
        return "
        <!DOCTYPE html><html lang='fr'><head><meta charset='UTF-8'></head>
        <body style='margin:0;padding:0;background:#f4f4f4;font-family:Arial,sans-serif'>
            <table width='100%' cellpadding='0' cellspacing='0' style='background:#f4f4f4;padding:32px 0'>
                <tr><td align='center'>
                    <table width='600' cellpadding='0' cellspacing='0' style='background:#fff;border-radius:8px;overflow:hidden;max-width:100%'>
                        <tr><td style='background:{$color};padding:24px 32px'>
                            <h1 style='margin:0;color:#fff;font-size:20px'>{$name}</h1>
                        </td></tr>
                        <tr><td style='padding:32px;color:#2C2C2C;font-size:15px;line-height:1.6'>
                            <h2 style='color:{$color};margin-top:0'>$titre</h2>
                            $body
                        </td></tr>
                        <tr><td style='background:{$fond};padding:16px 32px;font-size:12px;color:#5F6470;text-align:center'>
                            {$name} · {$adresse} · {$email}
                        </td></tr>
                    </table>
                </td></tr>
            </table>
        </body></html>";
    }

    private static function send(string $to, string $subject, string $html, string $text, ?string $replyTo = null, array $attachments = []): void {
        $apiKey = BREVO_API_KEY;
        if (!$apiKey) {
            throw new RuntimeException('BREVO_API_KEY non configurée.');
        }

        $payload = [
            'sender'      => ['name' => siteName(), 'email' => MAIL_FROM],
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

    public static function sendWelcome(string $email, string $prenom): void {
        try {
            $name  = siteName();
            $color = siteColor('couleur_principale');
            self::send(
                $email,
                'Bienvenue chez ' . $name . ' !',
                self::wrap("Bienvenue $prenom !", "
                    <p>Bienvenue chez <strong>" . htmlspecialchars($name) . "</strong> ! Votre compte a été créé avec succès.</p>
                    <p>Vous pouvez dès maintenant découvrir nos menus et passer commande.</p>
                    <p><a href='" . BASE_URL . "/menus' style='background:{$color};color:#fff;padding:10px 22px;border-radius:6px;text-decoration:none;display:inline-block;margin-top:8px'>Découvrir nos menus</a></p>
                "),
                "Bonjour $prenom, bienvenue chez $name ! Connectez-vous sur " . BASE_URL
            );
        } catch (\Throwable $e) {
            error_log("Erreur mail bienvenu : " . $e->getMessage());
        }
    }

    public static function sendPasswordReset(string $email, string $token): void {
        try {
            $link  = BASE_URL . "/reinitialiser?token=$token";
            $color = siteColor('couleur_principale');
            self::send(
                $email,
                'Réinitialisation de votre mot de passe',
                self::wrap('Réinitialisation du mot de passe', "
                    <p>Vous avez demandé la réinitialisation de votre mot de passe. Cliquez sur le bouton ci-dessous (lien valable <strong>1 heure</strong>) :</p>
                    <p style='text-align:center;margin:24px 0'><a href='$link' style='background:{$color};color:#fff;padding:12px 28px;border-radius:6px;text-decoration:none;display:inline-block'>Réinitialiser mon mot de passe</a></p>
                    <p style='color:#5F6470;font-size:13px'>Si vous n'avez pas fait cette demande, ignorez cet email. Votre mot de passe ne sera pas modifié.</p>
                "),
                "Réinitialisez votre mot de passe (valable 1h) : $link"
            );
        } catch (\Throwable $e) {
            error_log("Erreur mail reset : " . $e->getMessage());
        }
    }

    /**
     * $panier: array of session panier items (titre, nombre_personne, prix_par_personne)
     */
    public static function sendCommandeConfirmation(string $email, array $commande, array $panier): void {
        try {
            $lignesHtml = '';
            $totalMenusBrut = 0.0;
            foreach ($panier as $item) {
                $prixLigne = round((float)$item['prix_par_personne'] * (int)$item['nombre_personne'], 2);
                $lignesHtml .= "<tr><td style='padding:4px 0;color:#5F6470'>" . htmlspecialchars($item['titre']) . " (" . (int)$item['nombre_personne'] . " pers.)</td>"
                             . "<td style='padding:4px 0;text-align:right'>" . number_format($prixLigne, 2, ',', ' ') . " €</td></tr>";
                $totalMenusBrut += $prixLigne;
            }
            $prixLivraison = (float)($commande['prix_livraison'] ?? 0.0);
            $titresCsv = implode(', ', array_column($panier, 'titre'));

            self::send(
                $email,
                'Confirmation de votre commande #' . $commande['numero_commande'],
                self::wrap('Votre commande est confirmée !', "
                    <p>Bonjour,</p>
                    <p>Nous avons bien reçu votre commande. Voici le récapitulatif :</p>
                    <table style='width:100%;border-collapse:collapse;margin:16px 0'>
                        <tr><td style='padding:6px 0;color:#5F6470'>Numéro</td><td style='padding:6px 0'><strong>{$commande['numero_commande']}</strong></td></tr>
                        <tr><td style='padding:6px 0;color:#5F6470'>Date</td><td style='padding:6px 0'>" . htmlspecialchars($commande['date_prestation']) . " à " . htmlspecialchars($commande['heure_livraison']) . "</td></tr>
                        <tr style='background:" . siteColor('couleur_fond') . "'><td style='padding:6px 8px;color:#5F6470'>Adresse</td><td style='padding:6px 8px'>" . htmlspecialchars($commande['adresse_livraison'] . ', ' . $commande['ville_livraison']) . "</td></tr>
                    </table>
                    <table style='width:100%;border-collapse:collapse;margin:8px 0'>
                        <tr><th style='text-align:left;padding:4px 0;color:#5F6470;font-weight:normal'>Menus</th><th style='text-align:right;padding:4px 0;color:#5F6470;font-weight:normal'>Prix</th></tr>
                        $lignesHtml
                    </table>
                    <table style='width:100%;border-collapse:collapse;border-top:2px solid " . siteColor('couleur_principale') . ";margin-top:8px'>
                        <tr><td style='padding:6px 0;color:#5F6470'>Livraison</td><td style='padding:6px 0;text-align:right'>$prixLivraison €</td></tr>
                        <tr style='font-size:1.1em'><td style='padding:10px 0'><strong>Total</strong></td><td style='padding:10px 0;text-align:right;color:" . siteColor('couleur_principale') . "'><strong>{$commande['prix_total']} €</strong></td></tr>
                    </table>
                    <p>Merci pour votre confiance !<br>L'équipe " . htmlspecialchars(siteName()) . "</p>
                "),
                "Commande #{$commande['numero_commande']} confirmée — Menus : $titresCsv — Total : {$commande['prix_total']} €"
            );
        } catch (\Throwable $e) {
            error_log("Erreur mail commande : " . $e->getMessage());
        }
    }

    public static function sendCommandeTerminee(string $email, int $commandeId): void {
        try {
            $link  = BASE_URL . "/connexion?next=/mon-compte";
            $name  = siteName();
            $color = siteColor('couleur_principale');
            self::send(
                $email,
                'Votre avis nous intéresse !',
                self::wrap('Votre prestation est terminée !', "
                    <p>Nous espérons que vous avez été pleinement satisfait de notre prestation.</p>
                    <p>Votre retour est précieux pour nous aider à nous améliorer. Connectez-vous à votre espace client pour laisser votre avis :</p>
                    <p style='text-align:center;margin:24px 0'><a href='$link' style='background:{$color};color:#fff;padding:12px 28px;border-radius:6px;text-decoration:none;display:inline-block'>Donner mon avis</a></p>
                    <p>Merci de nous faire confiance,<br>L'équipe " . htmlspecialchars($name) . "</p>
                "),
                "Votre commande est terminée. Donnez votre avis sur $link"
            );
        } catch (\Throwable $e) {
            error_log("Erreur mail terminée : " . $e->getMessage());
        }
    }

    public static function sendDocumentFacturation(array $document, array $commande, string $archiveAbsolutePath): void {
        $email = trim((string)($document['client_email'] ?? ''));
        if (!$email) {
            throw new InvalidArgumentException('Email client manquant.');
        }
        if (!is_file($archiveAbsolutePath)) {
            throw new RuntimeException('Archive du document introuvable.');
        }

        $typeLabel = ($document['type_document'] ?? '') === 'ticket' ? 'ticket de caisse' : 'facture';
        $numero = $document['numero_document'] ?: ('document #' . (int)$document['document_id']);
        $filename = preg_replace('/[^A-Z0-9_-]+/i', '-', $numero) ?: 'document';
        $subject = ucfirst($typeLabel) . ' ' . $numero . ' - ' . siteName();
        $html = self::wrap(ucfirst($typeLabel) . ' disponible', "
            <p>Bonjour " . htmlspecialchars($document['client_nom'] ?? '') . ",</p>
            <p>Veuillez trouver ci-joint votre <strong>" . htmlspecialchars($typeLabel) . "</strong> lié à la commande <strong>" . htmlspecialchars($commande['numero_commande'] ?? '') . "</strong>.</p>
            <table style='width:100%;border-collapse:collapse;margin:16px 0'>
                <tr><td style='padding:6px 0;color:#5F6470'>Document</td><td style='padding:6px 0'><strong>" . htmlspecialchars($numero) . "</strong></td></tr>
                <tr><td style='padding:6px 0;color:#5F6470'>Total TTC</td><td style='padding:6px 0;color:" . siteColor('couleur_principale') . "'><strong>" . htmlspecialchars(formatPrice($document['total_ttc'] ?? 0)) . "</strong></td></tr>
            </table>
            <p>Merci pour votre confiance,<br>L'équipe " . htmlspecialchars(siteName()) . "</p>
        ");

        self::send(
            $email,
            $subject,
            $html,
            ucfirst($typeLabel) . " $numero - total " . formatPrice($document['total_ttc'] ?? 0),
            null,
            [[
                'name' => $filename . '.html',
                'content' => base64_encode(file_get_contents($archiveAbsolutePath)),
            ]]
        );
    }

    public static function sendMaterielRelance(string $email, string $prenom): void {
        try {
            $name      = siteName();
            $siteEmail = siteEmail();
            $phone     = sitePhone();
            $color2    = siteColor('couleur_secondaire');
            self::send(
                $email,
                'Retour de matériel - ' . $name,
                self::wrap("Retour de matériel — Action requise", "
                    <p>Bonjour $prenom,</p>
                    <p>Votre prestation comprenait du matériel prêté par " . htmlspecialchars($name) . ". Vous disposez de <strong>10 jours ouvrés</strong> à compter de la livraison pour le restituer.</p>
                    <div style='background:#FFF6DA;border-left:4px solid {$color2};padding:12px 16px;margin:16px 0;border-radius:4px'>
                        ⚠️ Passé ce délai, des frais de <strong>600 € TTC</strong> seront facturés conformément à nos CGV.
                    </div>
                    <p>Pour organiser le retour, contactez-nous :<br>
                    📧 <a href='mailto:" . htmlspecialchars($siteEmail) . "'>" . htmlspecialchars($siteEmail) . "</a>" .
                    ($phone ? "<br>📞 " . htmlspecialchars($phone) : '') . "</p>
                    <p>L'équipe " . htmlspecialchars($name) . "</p>
                "),
                "Bonjour $prenom, vous avez 10 jours ouvrés pour restituer le matériel prêté. Passé ce délai : 600 € de frais. Contact : $siteEmail"
            );
        } catch (\Throwable $e) {
            error_log("Erreur mail matériel : " . $e->getMessage());
        }
    }

    public static function sendEmployeCreation(string $email): void {
        try {
            $loginUrl = BASE_URL . "/connexion";
            $name     = siteName();
            $color    = siteColor('couleur_principale');
            $fond     = siteColor('couleur_fond');
            self::send(
                $email,
                'Votre compte employé ' . $name,
                self::wrap('Votre compte employé a été créé', "
                    <p>Bonjour,</p>
                    <p>Un compte employé a été créé pour vous sur la plateforme <strong>" . htmlspecialchars($name) . "</strong>.</p>
                    <table style='width:100%;border-collapse:collapse;margin:16px 0'>
                        <tr style='background:{$fond}'><td style='padding:8px;color:#5F6470'>Identifiant</td><td style='padding:8px'><strong>" . htmlspecialchars($email) . "</strong></td></tr>
                        <tr><td style='padding:8px;color:#5F6470'>Mot de passe</td><td style='padding:8px'>Communiqué directement par votre administrateur</td></tr>
                    </table>
                    <p style='text-align:center;margin:24px 0'><a href='$loginUrl' style='background:{$color};color:#fff;padding:12px 28px;border-radius:6px;text-decoration:none;display:inline-block'>Se connecter</a></p>
                    <p>L'équipe " . htmlspecialchars($name) . "</p>
                "),
                "Votre compte employé a été créé. Identifiant : $email. Mot de passe communiqué par l'administrateur. Connexion : $loginUrl"
            );
        } catch (\Throwable $e) {
            error_log("Erreur mail employé : " . $e->getMessage());
        }
    }

    public static function sendContact(string $titre, string $description, string $emailExp): void {
        try {
            $safeDesc = nl2br(htmlspecialchars($description));
            self::send(
                MAIL_FROM,
                "[Contact] $titre",
                self::wrap('Nouveau message de contact', "
                    <p><strong>De :</strong> " . htmlspecialchars($emailExp) . "</p>
                    <p><strong>Sujet :</strong> " . htmlspecialchars($titre) . "</p>
                    <hr style='border:none;border-top:1px solid #eee;margin:16px 0'>
                    <p>$safeDesc</p>
                "),
                "Message de $emailExp — $titre : $description",
                $emailExp
            );
        } catch (\Throwable $e) {
            error_log("Erreur mail contact : " . $e->getMessage());
        }
    }
}
