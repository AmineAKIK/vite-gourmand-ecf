<?php

namespace App\Controllers;

use App\Config\Configuration;
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
        $contactTitle = Configuration::get('content.contact.title');
        $contactIntro = Configuration::get('content.contact.intro');
        $seoTitle = Configuration::get('seo.contact.title');
        $metaDescription = Configuration::get('seo.contact.description');
        view('pages/contact', compact('sujet', 'contactTitle', 'contactIntro', 'seoTitle', 'metaDescription'));
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

        $slaHours = Configuration::get('contact.response_sla_hours');
        $message = 'Votre message a bien été envoyé.';
        if (is_int($slaHours) && $slaHours > 0) {
            $message .= ' Délai de réponse annoncé : ' . $slaHours . ' h.';
        }
        flash('success', $message);
        redirect('/contact');
    }
}
