<?php
// src/services/MailService.php
// Nécessite : composer require phpmailer/phpmailer

use PHPMailer\PHPMailer\PHPMailer;

class MailService {

    private static function wrap(string $titre, string $body): string {
        return "
        <!DOCTYPE html><html lang='fr'><head><meta charset='UTF-8'></head>
        <body style='margin:0;padding:0;background:#f4f4f4;font-family:Arial,sans-serif'>
            <table width='100%' cellpadding='0' cellspacing='0' style='background:#f4f4f4;padding:32px 0'>
                <tr><td align='center'>
                    <table width='600' cellpadding='0' cellspacing='0' style='background:#fff;border-radius:8px;overflow:hidden;max-width:100%'>
                        <tr><td style='background:#8B1A2B;padding:24px 32px'>
                            <h1 style='margin:0;color:#fff;font-size:20px'>Vite &amp; Gourmand</h1>
                        </td></tr>
                        <tr><td style='padding:32px;color:#2C2C2C;font-size:15px;line-height:1.6'>
                            <h2 style='color:#8B1A2B;margin-top:0'>$titre</h2>
                            $body
                        </td></tr>
                        <tr><td style='background:#FDF6EC;padding:16px 32px;font-size:12px;color:#5F6470;text-align:center'>
                            Vite &amp; Gourmand · 12 rue des Capucins, 33000 Bordeaux · contact@vitegourmand.fr
                        </td></tr>
                    </table>
                </td></tr>
            </table>
        </body></html>";
    }

    private static function mailer(): PHPMailer {
        if (!class_exists(PHPMailer::class)) {
            throw new RuntimeException('PHPMailer est indisponible. Lancez composer install.');
        }

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USER;
        $mail->Password   = MAIL_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = MAIL_PORT;
        $mail->CharSet    = 'UTF-8';
        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        return $mail;
    }

    public static function sendWelcome(string $email, string $prenom): void {
        try {
            $mail = self::mailer();
            $mail->addAddress($email);
            $mail->Subject = 'Bienvenue chez Vite & Gourmand !';
            $mail->isHTML(true);
            $mail->Body    = self::wrap("Bienvenue $prenom !", "
                <p>Bienvenue chez <strong>Vite &amp; Gourmand</strong> ! Votre compte a été créé avec succès.</p>
                <p>Vous pouvez dès maintenant découvrir nos menus et passer commande.</p>
                <p><a href='" . BASE_URL . "/menus' style='background:#8B1A2B;color:#fff;padding:10px 22px;border-radius:6px;text-decoration:none;display:inline-block;margin-top:8px'>Découvrir nos menus</a></p>
            ");
            $mail->AltBody = "Bonjour $prenom, bienvenue chez Vite & Gourmand ! Connectez-vous sur " . BASE_URL;
            $mail->send();
        } catch (\Throwable $e) {
            error_log("Erreur mail bienvenu : " . $e->getMessage());
        }
    }

    public static function sendPasswordReset(string $email, string $token): void {
        try {
            $mail = self::mailer();
            $mail->addAddress($email);
            $mail->Subject = 'Réinitialisation de votre mot de passe';
            $mail->isHTML(true);
            $link = BASE_URL . "/reinitialiser?token=$token";
            $mail->Body    = self::wrap('Réinitialisation du mot de passe', "
                <p>Vous avez demandé la réinitialisation de votre mot de passe. Cliquez sur le bouton ci-dessous (lien valable <strong>1 heure</strong>) :</p>
                <p style='text-align:center;margin:24px 0'><a href='$link' style='background:#8B1A2B;color:#fff;padding:12px 28px;border-radius:6px;text-decoration:none;display:inline-block'>Réinitialiser mon mot de passe</a></p>
                <p style='color:#5F6470;font-size:13px'>Si vous n'avez pas fait cette demande, ignorez cet email. Votre mot de passe ne sera pas modifié.</p>
            ");
            $mail->AltBody = "Réinitialisez votre mot de passe (valable 1h) : $link";
            $mail->send();
        } catch (\Throwable $e) {
            error_log("Erreur mail reset : " . $e->getMessage());
        }
    }

    public static function sendCommandeConfirmation(string $email, array $commande, array $menu): void {
        try {
            $mail = self::mailer();
            $mail->addAddress($email);
            $mail->Subject = 'Confirmation de votre commande #' . $commande['numero_commande'];
            $mail->isHTML(true);
            $mail->Body    = self::wrap('Votre commande est confirmée !', "
                <p>Bonjour,</p>
                <p>Nous avons bien reçu votre commande. Voici le récapitulatif :</p>
                <table style='width:100%;border-collapse:collapse;margin:16px 0'>
                    <tr><td style='padding:6px 0;color:#5F6470'>Numéro</td><td style='padding:6px 0'><strong>{$commande['numero_commande']}</strong></td></tr>
                    <tr style='background:#FDF6EC'><td style='padding:6px 8px;color:#5F6470'>Menu</td><td style='padding:6px 8px'><strong>" . htmlspecialchars($menu['titre']) . "</strong></td></tr>
                    <tr><td style='padding:6px 0;color:#5F6470'>Date</td><td style='padding:6px 0'>" . htmlspecialchars($commande['date_prestation']) . " à " . htmlspecialchars($commande['heure_livraison']) . "</td></tr>
                    <tr style='background:#FDF6EC'><td style='padding:6px 8px;color:#5F6470'>Adresse</td><td style='padding:6px 8px'>" . htmlspecialchars($commande['adresse_livraison'] . ', ' . $commande['ville_livraison']) . "</td></tr>
                    <tr><td style='padding:6px 0;color:#5F6470'>Personnes</td><td style='padding:6px 0'>{$commande['nombre_personne']}</td></tr>
                </table>
                <table style='width:100%;border-collapse:collapse;border-top:2px solid #8B1A2B;margin-top:8px'>
                    <tr><td style='padding:6px 0;color:#5F6470'>Prix menu</td><td style='padding:6px 0;text-align:right'>{$commande['prix_menu']} €</td></tr>
                    <tr><td style='padding:6px 0;color:#5F6470'>Livraison</td><td style='padding:6px 0;text-align:right'>{$commande['prix_livraison']} €</td></tr>
                    <tr style='font-size:1.1em'><td style='padding:10px 0'><strong>Total</strong></td><td style='padding:10px 0;text-align:right;color:#8B1A2B'><strong>{$commande['prix_total']} €</strong></td></tr>
                </table>
                <p>Merci pour votre confiance !<br>L'équipe Vite &amp; Gourmand</p>
            ");
            $mail->AltBody = "Commande #{$commande['numero_commande']} confirmée — Menu : {$menu['titre']} — Total : {$commande['prix_total']} €";
            $mail->send();
        } catch (\Throwable $e) {
            error_log("Erreur mail commande : " . $e->getMessage());
        }
    }

    public static function sendCommandeTerminee(string $email, int $commandeId): void {
        try {
            $mail = self::mailer();
            $mail->addAddress($email);
            $mail->Subject = 'Votre avis nous intéresse !';
            $mail->isHTML(true);
            $link = BASE_URL . "/mon-compte";
            $mail->Body    = self::wrap('Votre prestation est terminée !', "
                <p>Nous espérons que vous avez été pleinement satisfait de notre prestation.</p>
                <p>Votre retour est précieux pour nous aider à nous améliorer. Connectez-vous à votre espace client pour laisser votre avis :</p>
                <p style='text-align:center;margin:24px 0'><a href='$link' style='background:#8B1A2B;color:#fff;padding:12px 28px;border-radius:6px;text-decoration:none;display:inline-block'>Donner mon avis</a></p>
                <p>Merci de nous faire confiance,<br>L'équipe Vite &amp; Gourmand</p>
            ");
            $mail->AltBody = "Votre commande est terminée. Donnez votre avis sur $link";
            $mail->send();
        } catch (\Throwable $e) {
            error_log("Erreur mail terminée : " . $e->getMessage());
        }
    }

    public static function sendMaterielRelance(string $email, string $prenom): void {
        try {
            $mail = self::mailer();
            $mail->addAddress($email);
            $mail->Subject = 'Retour de matériel - Vite & Gourmand';
            $mail->isHTML(true);
            $mail->Body    = self::wrap("Retour de matériel — Action requise", "
                <p>Bonjour $prenom,</p>
                <p>Votre prestation comprenait du matériel prêté par Vite &amp; Gourmand. Vous disposez de <strong>10 jours ouvrés</strong> à compter de la livraison pour le restituer.</p>
                <div style='background:#FFF6DA;border-left:4px solid #D4A843;padding:12px 16px;margin:16px 0;border-radius:4px'>
                    ⚠️ Passé ce délai, des frais de <strong>600 € TTC</strong> seront facturés conformément à nos CGV.
                </div>
                <p>Pour organiser le retour, contactez-nous :<br>
                📧 <a href='mailto:contact@vitegourmand.fr'>contact@vitegourmand.fr</a><br>
                📞 05 56 00 12 34</p>
                <p>L'équipe Vite &amp; Gourmand</p>
            ");
            $mail->AltBody = "Bonjour $prenom, vous avez 10 jours ouvrés pour restituer le matériel prêté. Passé ce délai : 600 € de frais. Contact : contact@vitegourmand.fr";
            $mail->send();
        } catch (\Throwable $e) {
            error_log("Erreur mail matériel : " . $e->getMessage());
        }
    }

    public static function sendEmployeCreation(string $email): void {
        try {
            $mail = self::mailer();
            $mail->addAddress($email);
            $mail->Subject = 'Votre compte employé Vite & Gourmand';
            $mail->isHTML(true);
            $loginUrl = BASE_URL . "/connexion";
            $mail->Body    = self::wrap('Votre compte employé a été créé', "
                <p>Bonjour,</p>
                <p>Un compte employé a été créé pour vous sur la plateforme <strong>Vite &amp; Gourmand</strong>.</p>
                <table style='width:100%;border-collapse:collapse;margin:16px 0'>
                    <tr style='background:#FDF6EC'><td style='padding:8px;color:#5F6470'>Identifiant</td><td style='padding:8px'><strong>" . htmlspecialchars($email) . "</strong></td></tr>
                    <tr><td style='padding:8px;color:#5F6470'>Mot de passe</td><td style='padding:8px'>Communiqué directement par votre administrateur</td></tr>
                </table>
                <p style='text-align:center;margin:24px 0'><a href='$loginUrl' style='background:#8B1A2B;color:#fff;padding:12px 28px;border-radius:6px;text-decoration:none;display:inline-block'>Se connecter</a></p>
                <p>L'équipe Vite &amp; Gourmand</p>
            ");
            $mail->AltBody = "Votre compte employé a été créé. Identifiant : $email. Mot de passe communiqué par l'administrateur. Connexion : $loginUrl";
            $mail->send();
        } catch (\Throwable $e) {
            error_log("Erreur mail employé : " . $e->getMessage());
        }
    }

    public static function sendContact(string $titre, string $description, string $emailExp): void {
        try {
            $mail = self::mailer();
            $mail->addAddress(MAIL_FROM); // Envoi à l'entreprise
            $mail->addReplyTo($emailExp);
            $mail->Subject = "[Contact] $titre";
            $mail->isHTML(true);
            $safeDesc = nl2br(htmlspecialchars($description));
            $mail->Body    = self::wrap('Nouveau message de contact', "
                <p><strong>De :</strong> " . htmlspecialchars($emailExp) . "</p>
                <p><strong>Sujet :</strong> " . htmlspecialchars($titre) . "</p>
                <hr style='border:none;border-top:1px solid #eee;margin:16px 0'>
                <p>$safeDesc</p>
            ");
            $mail->AltBody = "Message de $emailExp — $titre : $description";
            $mail->send();
        } catch (\Throwable $e) {
            error_log("Erreur mail contact : " . $e->getMessage());
        }
    }
}
