<?php

namespace App\Controllers;

use App\Models\UserModel;
use App\Security\Password;
use App\Security\RateLimiter;
use App\Services\MailService;

class AuthController
{

    public function loginForm(): void {
        $next = $_GET['next'] ?? '';
        $allowed = ['/mon-compte'];
        if ($next && in_array($next, $allowed, true)) {
            $_SESSION['redirect_after_login'] = $next;
        }
        view('pages/auth/login');
    }

    public function login(): void {
        verifyCsrf();
        $ip       = RateLimiter::clientIp();
        $email    = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        try {
            RateLimiter::check($ip, 'login');
        } catch (\PDOException) {
            // Table rate_limit absente — fail open, ne pas bloquer la connexion
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
            redirect('/connexion');
        }

        if (!$email || !$password) {
            flash('error', 'Veuillez remplir tous les champs.');
            redirect('/connexion');
        }

        $user = UserModel::findByEmail($email);
        // Toujours appeler password_verify même si l'user est introuvable — évite le timing attack
        $hash = $user['password'] ?? Password::dummyHash();
        if (!$user || !password_verify($password, $hash)) {
            RateLimiter::record($ip, 'login');
            flash('error', 'Email ou mot de passe incorrect.');
            redirect('/connexion');
        }
        if (!$user['actif']) {
            flash('error', 'Votre compte a été désactivé. Contactez-nous.');
            redirect('/connexion');
        }
        // Vérification email uniquement si la colonne existe (migration 033 appliquée)
        if (array_key_exists('email_verified_at', $user) && empty($user['email_verified_at'])) {
            flash('error', 'Veuillez confirmer votre adresse email avant de vous connecter. Vérifiez vos spams.');
            redirect('/connexion');
        }

        RateLimiter::reset($ip, 'login');

        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id'                  => $user['utilisateur_id'],
            'email'               => $user['email'],
            'prenom'              => $user['prenom'],
            'nom'                 => $user['nom'],
            'role'                => $user['role_libelle'],
            'must_change_password'=> !empty($user['must_change_password']),
        ];
        $_SESSION['last_activity'] = time();

        if (!empty($user['must_change_password'])) {
            redirect('/employe/changer-mot-de-passe');
        }

        $redirect = $_SESSION['redirect_after_login'] ?? roleHomePath($user['role_libelle']);
        unset($_SESSION['redirect_after_login']);
        redirect($redirect);
    }

    public function registerForm(): void {
        view('pages/auth/register');
    }

    public function register(): void {
        verifyCsrf();
        $ip = RateLimiter::clientIp();
        try {
            RateLimiter::check($ip, 'register', 3, 3600);
        } catch (\PDOException) {
            // Table rate_limit absente — fail open
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
            redirect('/inscription');
        }

        $data = [
            'email'         => sanitize($_POST['email'] ?? ''),
            'prenom'        => sanitize($_POST['prenom'] ?? ''),
            'nom'           => sanitize($_POST['nom'] ?? ''),
            'telephone'     => sanitize($_POST['telephone'] ?? ''),
            'adresse'       => sanitize($_POST['adresse'] ?? ''),
            'ville'         => sanitize($_POST['ville'] ?? ''),
            'code_postal'   => sanitize($_POST['code_postal'] ?? ''),
            'password'      => $_POST['password'] ?? '',
        ];

        // Validations
        if (!$data['prenom'] || !$data['nom'] || !$data['telephone'] || !$data['adresse'] || !$data['ville'] || !$data['code_postal'] || !$data['password']) {
            flash('error', 'Tous les champs sont obligatoires.');
            redirect('/inscription');
        }
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Email invalide.'); redirect('/inscription');
        }
        if ($data['password'] !== ($_POST['password_confirm'] ?? '')) {
            flash('error', 'Les mots de passe ne correspondent pas.'); redirect('/inscription');
        }
        if (!validatePassword($data['password'])) {
            flash('error', passwordPolicyMessage()); redirect('/inscription');
        }
        if (UserModel::findByEmail($data['email'])) {
            flash('error', 'Cet email est déjà utilisé.'); redirect('/inscription');
        }

        $token = bin2hex(random_bytes(32));
        $data['password'] = hashPassword($data['password']);
        $data['email_verification_token'] = $token;
        UserModel::create($data);

        RateLimiter::record($ip, 'register');
        MailService::sendEmailVerification($data['email'], $data['prenom'], $token);

        flash('success', 'Compte créé ! Vérifiez votre boîte email pour activer votre compte.');
        redirect('/inscription');
    }

    public function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        redirect('/');
    }

    public function verifyEmail(): void {
        $token = sanitize($_GET['token'] ?? '');
        if (!$token) {
            flash('error', 'Lien de vérification invalide.');
            redirect('/connexion');
        }
        $row = UserModel::verifyEmail($token);
        if (!$row) {
            flash('error', 'Ce lien est invalide ou a déjà été utilisé.');
            redirect('/connexion');
        }
        flash('success', 'Adresse email confirmée ! Vous pouvez maintenant vous connecter.');
        redirect('/connexion');
    }

    public function forgotForm(): void {
        view('pages/auth/forgot');
    }

    public function forgot(): void {
        verifyCsrf();
        $email = sanitize($_POST['email'] ?? '');
        $user  = UserModel::findByEmail($email);

        // Toujours afficher le même message (sécurité anti-enumération)
        flash('success', 'Si cet email existe, un lien de réinitialisation vous a été envoyé.');

        if ($user) {
            $token = bin2hex(random_bytes(32));
            UserModel::saveResetToken($user['utilisateur_id'], $token);
            MailService::sendPasswordReset($email, $token);
        }
        redirect('/mot-de-passe-oublie');
    }

    public function resetForm(): void {
        $token = sanitize($_GET['token'] ?? '');
        $tokenData = UserModel::findResetToken($token);
        if (!$tokenData) {
            flash('error', 'Lien invalide ou expiré.');
            redirect('/connexion');
        }
        view('pages/auth/reset', ['token' => $token]);
    }

    public function reset(): void {
        verifyCsrf();
        $token    = sanitize($_POST['token'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['password_conf'] ?? '';

        $tokenData = UserModel::findResetToken($token);
        if (!$tokenData) { flash('error', 'Lien invalide.'); redirect('/connexion'); }
        if ($password !== $confirm) { flash('error', 'Mots de passe différents.'); redirect("/reinitialiser?token=$token"); }
        if (!validatePassword($password)) { flash('error', passwordPolicyMessage()); redirect("/reinitialiser?token=$token"); }

        UserModel::updatePassword($tokenData['utilisateur_id'], hashPassword($password));
        UserModel::invalidateResetToken($token);

        flash('success', 'Mot de passe réinitialisé !');
        redirect('/connexion');
    }
}
