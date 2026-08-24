<?php

namespace App\Controllers;

use App\Domain\InputPolicy;
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
        $ip = RateLimiter::clientIp();
        try {
            $email = InputPolicy::email($_POST['email'] ?? '');
        } catch (\InvalidArgumentException) {
            $email = '';
        }
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

        try {
            RateLimiter::check($ip, 'login');
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
            redirect('/connexion');
        }

        if (!$email || !$password) {
            RateLimiter::record($ip, 'login');
            flash('error', 'Veuillez remplir tous les champs.');
            redirect('/connexion');
        }

        $user = UserModel::findByEmail($email);
        $hash = $user['password'] ?? Password::dummyHash();
        if (!$user || !password_verify($password, $hash)) {
            RateLimiter::record($ip, 'login');
            flash('error', 'Email ou mot de passe incorrect.');
            redirect('/connexion');
        }
        if (!$user['actif']) {
            RateLimiter::record($ip, 'login');
            flash('error', 'Votre compte a été désactivé. Contactez-nous.');
            redirect('/connexion');
        }
        if (array_key_exists('email_verified_at', $user) && empty($user['email_verified_at'])) {
            RateLimiter::record($ip, 'login');
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
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
            redirect('/inscription');
        }

        try {
            $data = [
                'email'       => InputPolicy::email($_POST['email'] ?? ''),
                'prenom'      => InputPolicy::text($_POST['prenom'] ?? '', 80, true),
                'nom'         => InputPolicy::text($_POST['nom'] ?? '', 100, true),
                'telephone'   => InputPolicy::text($_POST['telephone'] ?? '', 30, true),
                'adresse'     => InputPolicy::text($_POST['adresse'] ?? '', 180, true),
                'ville'       => InputPolicy::text($_POST['ville'] ?? '', 100, true),
                'code_postal' => InputPolicy::postalCode($_POST['code_postal'] ?? ''),
                'password'    => is_string($_POST['password'] ?? null) ? $_POST['password'] : '',
            ];
        } catch (\InvalidArgumentException $e) {
            RateLimiter::record($ip, 'register');
            flash('error', $e->getMessage());
            redirect('/inscription');
        }

        if ($data['password'] === '') {
            RateLimiter::record($ip, 'register');
            flash('error', 'Tous les champs sont obligatoires.');
            redirect('/inscription');
        }
        if ($data['password'] !== ($_POST['password_confirm'] ?? '')) {
            RateLimiter::record($ip, 'register');
            flash('error', 'Les mots de passe ne correspondent pas.'); redirect('/inscription');
        }
        if (!validatePassword($data['password'])) {
            RateLimiter::record($ip, 'register');
            flash('error', passwordPolicyMessage()); redirect('/inscription');
        }
        if (UserModel::findByEmail($data['email'])) {
            RateLimiter::record($ip, 'register');
            flash('error', 'Cet email est déjà utilisé.'); redirect('/inscription');
        }

        $token = bin2hex(random_bytes(32));
        $data['password'] = hashPassword($data['password']);
        $data['email_verification_token'] = $token;
        UserModel::create($data);

        RateLimiter::reset($ip, 'register');
        MailService::sendEmailVerification($data['email'], $data['prenom'], $token);

        flash('success', 'Compte créé ! Vérifiez votre boîte email pour activer votre compte.');
        redirect('/inscription');
    }

    public function logout(): void
    {
        verifyCsrf();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        redirect('/');
    }

    public function verifyEmail(): void {
        try {
            $token = InputPolicy::token($_GET['token'] ?? '', 128);
        } catch (\InvalidArgumentException) {
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
        $ip = RateLimiter::clientIp();
        try {
            RateLimiter::check($ip, 'forgot_password', 5, 3600);
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
            redirect('/mot-de-passe-oublie');
        }
        RateLimiter::record($ip, 'forgot_password');

        try {
            $email = InputPolicy::email($_POST['email'] ?? '');
        } catch (\InvalidArgumentException) {
            $email = '';
        }
        $user = $email !== '' ? UserModel::findByEmail($email) : null;

        flash('success', 'Si cet email existe, un lien de réinitialisation vous a été envoyé.');

        if ($user) {
            $token = bin2hex(random_bytes(32));
            UserModel::saveResetToken($user['utilisateur_id'], $token);
            MailService::sendPasswordReset($email, $token);
        }
        redirect('/mot-de-passe-oublie');
    }

    public function resetForm(): void {
        try {
            $token = InputPolicy::token($_GET['token'] ?? '', 128);
        } catch (\InvalidArgumentException) {
            flash('error', 'Lien invalide ou expiré.');
            redirect('/connexion');
        }
        $tokenData = UserModel::findResetToken($token);
        if (!$tokenData) {
            flash('error', 'Lien invalide ou expiré.');
            redirect('/connexion');
        }
        view('pages/auth/reset', ['token' => $token]);
    }

    public function reset(): void {
        verifyCsrf();
        $ip = RateLimiter::clientIp();
        try {
            RateLimiter::check($ip, 'reset_password', 10, 900);
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
            redirect('/connexion');
        }
        RateLimiter::record($ip, 'reset_password');

        try {
            $token = InputPolicy::token($_POST['token'] ?? '', 128);
        } catch (\InvalidArgumentException) {
            flash('error', 'Lien invalide.');
            redirect('/connexion');
        }
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        $confirm  = is_string($_POST['password_conf'] ?? null) ? $_POST['password_conf'] : '';

        $tokenData = UserModel::findResetToken($token);
        if (!$tokenData) { flash('error', 'Lien invalide.'); redirect('/connexion'); }
        if ($password !== $confirm) { flash('error', 'Mots de passe différents.'); redirect('/reinitialiser?token=' . rawurlencode($token)); }
        if (!validatePassword($password)) { flash('error', passwordPolicyMessage()); redirect('/reinitialiser?token=' . rawurlencode($token)); }

        UserModel::updatePassword($tokenData['utilisateur_id'], hashPassword($password));
        UserModel::invalidateResetToken($token);
        RateLimiter::reset($ip, 'reset_password');

        flash('success', 'Mot de passe réinitialisé !');
        redirect('/connexion');
    }
}
