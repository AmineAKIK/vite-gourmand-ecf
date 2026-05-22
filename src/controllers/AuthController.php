<?php
// src/controllers/AuthController.php

class AuthController {

    public function loginForm(): void {
        view('pages/auth/login');
    }

    public function login(): void {
        verifyCsrf();
        $email    = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!$email || !$password) {
            flash('error', 'Veuillez remplir tous les champs.');
            redirect('/connexion');
        }

        $user = UserModel::findByEmail($email);
        if (!$user || !password_verify($password, $user['password'])) {
            flash('error', 'Email ou mot de passe incorrect.');
            redirect('/connexion');
        }
        if (!$user['actif']) {
            flash('error', 'Votre compte a été désactivé. Contactez-nous.');
            redirect('/connexion');
        }

        $_SESSION['user'] = [
            'id'     => $user['utilisateur_id'],
            'email'  => $user['email'],
            'prenom' => $user['prenom'],
            'nom'    => $user['nom'],
            'role'   => $user['role_libelle'],
        ];

        $redirect = $_SESSION['redirect_after_login'] ?? roleHomePath($user['role_libelle']);
        unset($_SESSION['redirect_after_login']);
        redirect($redirect);
    }

    public function registerForm(): void {
        view('pages/auth/register');
    }

    public function register(): void {
        verifyCsrf();
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
        if (!validatePassword($data['password'])) {
            flash('error', passwordPolicyMessage()); redirect('/inscription');
        }
        if (UserModel::findByEmail($data['email'])) {
            flash('error', 'Cet email est déjà utilisé.'); redirect('/inscription');
        }

        $data['password'] = hashPassword($data['password']);
        UserModel::create($data);

        // Mail de bienvenue
        MailService::sendWelcome($data['email'], $data['prenom']);

        flash('success', 'Compte créé ! Vous pouvez vous connecter.');
        redirect('/connexion');
    }

    public function logout(): void {
        session_destroy();
        redirect('/');
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
