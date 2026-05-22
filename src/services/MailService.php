<?php
// src/services/MailService.php
// Nécessite : composer require phpmailer/phpmailer

use PHPMailer\PHPMailer\PHPMailer;

class MailService {

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
            $mail->Body = "
                <h2>Bonjour $prenom,</h2>
                <p>Bienvenue chez <strong>Vite & Gourmand</strong> ! Votre compte a été créé avec succès.</p>
                <p>Vous pouvez dès maintenant découvrir nos menus et passer commande.</p>
                <p>À très bientôt,<br>L'équipe Vite & Gourmand</p>
            ";
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
            $mail->Body = "
                <h2>Réinitialisation du mot de passe</h2>
                <p>Cliquez sur le lien ci-dessous pour réinitialiser votre mot de passe (valable 1 heure) :</p>
                <p><a href='$link'>$link</a></p>
                <p>Si vous n'avez pas fait cette demande, ignorez cet email.</p>
            ";
            $mail->send();
        } catch (\Throwable $e) {
            error_log("Erreur mail reset : " . $e->getMessage());
        }
    }

    public static function sendCommandeConfirmation(string $email, array $commande, array $menu): void {
        try {
            $mail = self::mailer();
            $mail->addAddress($email);
            $mail->Subject = 'Confirmation de commande #' . $commande['numero_commande'];
            $mail->isHTML(true);
            $mail->Body = "
                <h2>Votre commande est confirmée !</h2>
                <p>Numéro : <strong>{$commande['numero_commande']}</strong></p>
                <p>Menu : <strong>{$menu['titre']}</strong></p>
                <p>Date : {$commande['date_prestation']} à {$commande['heure_livraison']}</p>
                <p>Adresse : {$commande['adresse_livraison']}, {$commande['ville_livraison']}</p>
                <p>Nombre de personnes : {$commande['nombre_personne']}</p>
                <hr>
                <p>Prix menu : {$commande['prix_menu']} €</p>
                <p>Livraison : {$commande['prix_livraison']} €</p>
                <p><strong>Total : {$commande['prix_total']} €</strong></p>
                <p>Merci pour votre confiance !<br>L'équipe Vite & Gourmand</p>
            ";
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
            $mail->Body = "
                <h2>Votre commande est terminée !</h2>
                <p>Nous espérons que la prestation vous a satisfait. Connectez-vous pour laisser votre avis :</p>
                <p><a href='$link'>Mon espace client</a></p>
                <p>L'équipe Vite & Gourmand</p>
            ";
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
            $mail->Body = "
                <h2>Bonjour $prenom,</h2>
                <p>Votre commande inclut du matériel prêté. Vous disposez de <strong>10 jours ouvrés</strong> pour le restituer.</p>
                <p>Passé ce délai, des frais de <strong>600 €</strong> seront facturés (conformément aux CGV).</p>
                <p>Pour organiser le retour, contactez-nous par email ou téléphone.</p>
                <p>L'équipe Vite & Gourmand</p>
            ";
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
            $mail->Body = "
                <h2>Votre compte a été créé</h2>
                <p>Un compte employé a été créé pour vous sur la plateforme Vite & Gourmand.</p>
                <p>Votre identifiant est : <strong>$email</strong></p>
                <p>Le mot de passe vous sera communiqué directement par votre administrateur.</p>
                <p>Connectez-vous sur : <a href='" . BASE_URL . "/connexion'>" . BASE_URL . "</a></p>
            ";
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
            $mail->Body = "
                <h2>Nouveau message de contact</h2>
                <p><strong>De :</strong> $emailExp</p>
                <p><strong>Sujet :</strong> $titre</p>
                <p><strong>Message :</strong></p>
                <p>" . nl2br(htmlspecialchars($description)) . "</p>
            ";
            $mail->send();
        } catch (\Throwable $e) {
            error_log("Erreur mail contact : " . $e->getMessage());
        }
    }
}
