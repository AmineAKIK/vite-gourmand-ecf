<?php

namespace App\Security;

class Guard
{
    public static function requireAuth(): void
    {
        if (empty($_SESSION['user'])) {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
            header('Location: /connexion');
            exit;
        }
    }

    public static function requireRole(array $roles): void
    {
        self::requireAuth();
        if (!in_array($_SESSION['user']['role'] ?? '', $roles)) {
            http_response_code(403);
            \App\Core\View::render('pages/403');
            exit;
        }
        if (
            !empty($_SESSION['user']['must_change_password'])
            && $_SERVER['REQUEST_URI'] !== '/employe/changer-mot-de-passe'
        ) {
            header('Location: /employe/changer-mot-de-passe');
            exit;
        }
    }

    public static function isAuth(): bool
    {
        return !empty($_SESSION['user']);
    }

    public static function currentUser(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function hasRole(string $role): bool
    {
        return ($_SESSION['user']['role'] ?? '') === $role;
    }

    public static function isEmployeOrAdmin(): bool
    {
        return in_array($_SESSION['user']['role'] ?? '', [ROLE_EMPLOYE, ROLE_ADMIN]);
    }
}
