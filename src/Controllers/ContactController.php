<?php

namespace App\Controllers;

use App\Domain\InputPolicy;
use App\Security\RateLimiter;
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
        $ip = RateLimiter::clientIp();
        try {
            RateLimiter::check($ip, 'contact', 5, 3600);
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
            redirect('/contact');
        }
        RateLimiter::record($ip, 'contact');

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
