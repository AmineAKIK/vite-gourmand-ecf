<?php

namespace App\Core;

use App\Models\HoraireModel;

class View
{
    private static string $viewsPath = '';

    private static function path(): string
    {
        if (self::$viewsPath === '') {
            self::$viewsPath = dirname(__DIR__) . '/Views';
        }
        return self::$viewsPath;
    }

    public static function render(string $template, array $data = []): void
    {
        $isWorkspace = (str_starts_with($template, 'pages/admin/')
                    || str_starts_with($template, 'pages/employe/'))
                    && $template !== 'pages/employe/change_password';

        if (!$isWorkspace && !array_key_exists('siteHoraires', $data)) {
            $data['siteHoraires'] = HoraireModel::getAll();
        }

        $file = self::path() . '/' . $template . '.php';
        if (!file_exists($file)) {
            http_response_code(500);
            error_log("Vue introuvable : $template");
            exit;
        }

        $content = self::capture($file, $data);
        $layout  = $isWorkspace ? 'workspace' : 'main';
        self::renderLayout($layout, $content, $data);
    }

    public static function partial(string $template, array $data = []): void
    {
        $file = self::path() . '/' . $template . '.php';
        if (!file_exists($file)) {
            error_log("Partial introuvable : $template");
            return;
        }
        self::includeIsolated($file, $data);
    }

    public static function redirect(string $url): never
    {
        // Interdit les redirections ouvertes vers des domaines externes
        if (
            !str_starts_with($url, '/')
            && !preg_match('#^https?://' . preg_quote($_SERVER['HTTP_HOST'] ?? '', '#') . '#', $url)
        ) {
            $url = '/';
        }
        header("Location: $url");
        exit;
    }

    public static function currentPath(): string
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        return rtrim($path, '/') ?: '/';
    }

    public static function routeIsActive(string|array $patterns): bool
    {
        $current = self::currentPath();
        foreach ((array)$patterns as $pattern) {
            $pattern = rtrim($pattern, '/') ?: '/';
            if (str_ends_with($pattern, '*')) {
                $prefix = rtrim(substr($pattern, 0, -1), '/') ?: '/';
                if ($current === $prefix || str_starts_with($current, $prefix . '/')) {
                    return true;
                }
                continue;
            }
            if ($current === $pattern) {
                return true;
            }
        }
        return false;
    }

    public static function roleHomePath(?string $role = null): string
    {
        $role = $role ?? ($_SESSION['user']['role'] ?? '');
        return match ($role) {
            ROLE_ADMIN   => '/admin',
            ROLE_EMPLOYE => '/employe',
            default      => '/',
        };
    }

    public static function roleHomeLabel(?string $role = null): string
    {
        $role = $role ?? ($_SESSION['user']['role'] ?? '');
        return match ($role) {
            ROLE_ADMIN   => 'Espace administrateur',
            ROLE_EMPLOYE => 'Espace employé',
            default      => 'Mon compte',
        };
    }

    public static function roleWorkspaceIsActive(?string $role = null): bool
    {
        $role = $role ?? ($_SESSION['user']['role'] ?? '');
        return match ($role) {
            ROLE_ADMIN   => self::routeIsActive(['/admin*', '/employe*']),
            ROLE_EMPLOYE => self::routeIsActive('/employe*'),
            default      => false,
        };
    }

    public static function workspaceNavItems(): array
    {
        $isAdmin = \App\Security\Guard::hasRole(ROLE_ADMIN);
        $items   = [];

        $dashHref  = $isAdmin ? '/admin' : '/employe';
        $dashMatch = $isAdmin ? '/admin' : '/employe';
        $items[] = ['href' => $dashHref, 'label' => 'Tableau de bord', 'icon' => 'bi-speedometer2', 'match' => $dashMatch, 'exact' => true];

        $items[] = ['href' => '/employe/commandes', 'label' => 'Commandes',    'icon' => 'bi-list-check',   'match' => ['/employe/commandes*', '/employe/document*']];
        $items[] = ['href' => '/employe/menus',     'label' => 'Menus & Plats','icon' => 'bi-journal-text', 'match' => '/employe/menus*'];
        $items[] = ['href' => '/employe/avis',      'label' => 'Avis clients', 'icon' => 'bi-star',         'match' => '/employe/avis*'];

        if ($isAdmin) {
            $items[] = ['separator' => true];
            $items[] = ['href' => '/admin/employes',   'label' => 'Équipe',      'icon' => 'bi-people',  'match' => '/admin/employes*'];
            $items[] = ['href' => '/admin/stats',      'label' => 'Finances',    'icon' => 'bi-graph-up','match' => ['/admin/stats*', '/admin/comptabilite*']];
            $items[] = ['href' => '/admin/parametres', 'label' => 'Paramètres', 'icon' => 'bi-sliders', 'match' => ['/admin/parametres*', '/admin/accueil*']];
        }

        return $items;
    }

    public static function cspNonce(): string
    {
        return $GLOBALS['csp_nonce'] ?? '';
    }

    public static function imageUrl(?string $path, string $fallback = 'images/menu-placeholder.webp'): string
    {
        if (!$path) {
            return '/' . $fallback;
        }
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }
        return '/' . ltrim($path, '/');
    }

    public static function buildPageTitle(string $section = ''): string
    {
        $name = \App\Config\SiteConfig::name();
        return $section ? $section . ' — ' . $name : $name;
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    private static function capture(string $file, array $data): string
    {
        ob_start();
        self::includeIsolated($file, $data);
        return ob_get_clean();
    }

    private static function renderLayout(string $layout, string $content, array $data): void
    {
        $file = self::path() . '/layouts/' . $layout . '.php';
        // $content is available as a variable inside the layout
        self::includeIsolated($file, array_merge($data, ['content' => $content]));
    }

    private static function includeIsolated(string $file, array $data): void
    {
        // Use a closure to create an isolated scope — avoids extract() pollution
        $render = static function (string $_file, array $_data): void {
            foreach ($_data as $_k => $_v) {
                $$_k = $_v;
            }
            unset($_k, $_v, $_data);
            require $_file;
        };
        $render($file, $data);
    }
}
