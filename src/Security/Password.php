<?php

namespace App\Security;

class Password
{
    public static function hash(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    // Hash stable utilisé pour éviter le timing attack dans login() :
    // password_verify() est toujours appelé même quand l'email est introuvable.
    public static function dummyHash(): string
    {
        static $hash = null;
        if ($hash === null) {
            $hash = password_hash('__dummy__', PASSWORD_BCRYPT, ['cost' => 12]);
        }
        return $hash;
    }

    public static function validate(string $password): bool
    {
        return strlen($password) >= 10
            && preg_match('/[A-Z]/', $password)
            && preg_match('/[a-z]/', $password)
            && preg_match('/[0-9]/', $password)
            && preg_match('/[\W_]/', $password);
    }

    public static function policyMessage(): string
    {
        return 'Mot de passe trop faible (10 car. min, 1 maj, 1 min, 1 chiffre, 1 spécial).';
    }

    public static function policyRules(): array
    {
        return [
            'Au moins 10 caractères',
            'Au moins une majuscule (A-Z)',
            'Au moins une minuscule (a-z)',
            'Au moins un chiffre (0-9)',
            'Au moins un caractère spécial',
        ];
    }
}
