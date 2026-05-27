<?php

namespace App\Controllers;

use App\Services\MailService;

class ContactController
{
    public function index(): void
    {
        view('pages/contact');
    }

    public function send(): void
    {
        verifyCsrf();

        $titre       = sanitize($_POST['titre']       ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $email       = sanitize($_POST['email']       ?? '');

        if (!$titre || !$description || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Veuillez remplir tous les champs correctement.');
            redirect('/contact');
        }

        MailService::sendContact($titre, $description, $email);

        flash('success', 'Votre message a bien été envoyé ! Nous vous répondrons sous 48h.');
        redirect('/contact');
    }
}
