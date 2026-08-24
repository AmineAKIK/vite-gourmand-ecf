<?php

namespace App\Controllers;

use App\Domain\InputPolicy;
use App\Services\MailService;

class ContactController
{
    public function index(): void
    {
        try {
            $sujet = InputPolicy::text($_GET['sujet'] ?? '', 160);
        } catch (\InvalidArgumentException) {
            $sujet = '';
        }
        view('pages/contact', compact('sujet'));
    }

    public function send(): void
    {
        verifyCsrf();

        try {
            $titre       = InputPolicy::text($_POST['titre'] ?? '', 160, true);
            $description = InputPolicy::multiline($_POST['description'] ?? '', 5000, true);
            $email       = InputPolicy::email($_POST['email'] ?? '');
        } catch (\InvalidArgumentException $e) {
            flash('error', $e->getMessage());
            redirect('/contact');
        }

        MailService::sendContact($titre, $description, $email);

        flash('success', 'Votre message a bien été envoyé ! Nous vous répondrons sous 48h.');
        redirect('/contact');
    }
}
