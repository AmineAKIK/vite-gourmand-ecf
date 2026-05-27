<?php

/**
 * Compatibility shims — thin wrappers that delegate to namespaced classes.
 * Do NOT add logic here. Put it in the class instead.
 */

use App\Config\Database;
use App\Config\SiteConfig;
use App\Core\Formatter;
use App\Core\Session;
use App\Core\View;
use App\Domain\OrderStatus;
use App\Geo\DeliveryResolver;
use App\Models\SiteConfigModel;
use App\Security\Csrf;
use App\Security\Guard;
use App\Security\Password;

// ---------------------------------------------------------------------------
// Auth
// ---------------------------------------------------------------------------

function requireAuth(): void                        { Guard::requireAuth(); }
function requireRole(array $roles): void            { Guard::requireRole($roles); }
function isAuth(): bool                             { return Guard::isAuth(); }
function currentUser(): ?array                      { return Guard::currentUser(); }
function hasRole(string $role): bool                { return Guard::hasRole($role); }
function isEmployeOrAdmin(): bool                   { return Guard::isEmployeOrAdmin(); }

// ---------------------------------------------------------------------------
// CSRF
// ---------------------------------------------------------------------------

function csrf(): string                             { return Csrf::token(); }
function csrfField(): string                        { return Csrf::field(); }
function verifyCsrf(): void                         { Csrf::verify(); }

// ---------------------------------------------------------------------------
// Password
// ---------------------------------------------------------------------------

function validatePassword(string $password): bool  { return Password::validate($password); }
function hashPassword(string $password): string     { return Password::hash($password); }
function passwordPolicyMessage(): string            { return Password::policyMessage(); }
function passwordPolicyRules(): array               { return Password::policyRules(); }

// ---------------------------------------------------------------------------
// Flash / Session
// ---------------------------------------------------------------------------

function flash(string $key, string $message): void  { Session::flash($key, $message); }
function getFlash(string $key): ?string             { return Session::getFlash($key); }

// ---------------------------------------------------------------------------
// View / Routing
// ---------------------------------------------------------------------------

function view(string $template, array $data = []): void     { View::render($template, $data); }
function partial(string $template, array $data = []): void  { View::partial($template, $data); }
function redirect(string $url): never                       { View::redirect($url); }
function currentPath(): string                              { return View::currentPath(); }
function routeIsActive(string|array $patterns): bool        { return View::routeIsActive($patterns); }
function roleHomePath(?string $role = null): string         { return View::roleHomePath($role); }
function roleHomeLabel(?string $role = null): string        { return View::roleHomeLabel($role); }
function roleWorkspaceIsActive(?string $role = null): bool  { return View::roleWorkspaceIsActive($role); }
function workspaceNavItems(): array                         { return View::workspaceNavItems(); }
function cspNonce(): string                                 { return View::cspNonce(); }
function imageUrl(?string $path, string $fallback = 'images/menu-placeholder.webp'): string
{
    return View::imageUrl($path, $fallback);
}
function buildPageTitle(string $section = ''): string       { return View::buildPageTitle($section); }

// ---------------------------------------------------------------------------
// Formatting
// ---------------------------------------------------------------------------

function sanitize(?string $val): string                     { return Formatter::escape($val); }
function formatDateFr(?string $date, string $fallback = '—'): string
{
    return Formatter::dateFr($date, $fallback);
}
function formatDateTimeFr(?string $date, string $fallback = '—'): string
{
    return Formatter::dateTimeFr($date, $fallback);
}
function formatPrice(float|int|string|null $amount, int $decimals = 2): string
{
    return Formatter::price($amount, $decimals);
}
function formatPriceInput(float|int|string|null $amount): string
{
    return Formatter::priceInput($amount);
}
function formatInteger(float|int|string|null $amount): string   { return Formatter::integer($amount); }
function tomorrowDateInput(): string                            { return Formatter::tomorrowDateInput(); }
function personFullName(array $person): string                  { return Formatter::personFullName($person); }

// ---------------------------------------------------------------------------
// Order statuses
// ---------------------------------------------------------------------------

function commandeStatuses(): array                          { return OrderStatus::all(); }
function commandeCancelledStatus(): string                  { return OrderStatus::cancelled(); }
function commandeCompletedStatus(): string                  { return OrderStatus::completed(); }
function commandeAwaitingMaterialStatus(): string           { return OrderStatus::awaitingMaterial(); }
function commandeCountsTowardRevenue(?string $s): bool      { return OrderStatus::countsTowardRevenue($s); }
function commandeStatusIsValid(string $status): bool        { return OrderStatus::isValid($status); }
function commandeStatusLabel(?string $status): string       { return OrderStatus::label($status); }
function commandeStatusBadge(?string $status): string       { return OrderStatus::badge($status); }
function commandeCanTransition(?string $from, string $to): bool
{
    return OrderStatus::canTransition($from, $to);
}
function commandeCanClientModify(array $commande): bool     { return OrderStatus::clientCanModify($commande); }
function commandeCanClientTrack(?string $status): bool      { return OrderStatus::clientCanTrack($status); }
function commandeCanReview(?string $status): bool           { return OrderStatus::clientCanReview($status); }

// ---------------------------------------------------------------------------
// Payment badges (no dedicated class yet — kept here until PaymentService)
// ---------------------------------------------------------------------------

function paiementTypeLabel(string $type): string
{
    return match ($type) {
        'acompte'         => 'Acompte',
        'solde'           => 'Solde',
        'paiement_unique' => 'Paiement unique',
        default           => ucfirst($type),
    };
}

function paiementStatusBadge(string $statut): string
{
    [$cls, $label] = match ($statut) {
        'solde'   => ['statut-termine',   'Soldé'],
        'acompte' => ['statut-en_cours',  'Acompte versé'],
        default   => ['statut-en_attente', 'Non payé'],
    };
    return '<span class="badge-statut ' . $cls . '">' . $label . '</span>';
}

// ---------------------------------------------------------------------------
// Geo / Delivery
// ---------------------------------------------------------------------------

function normalizeLocationLabel(string $value): string      { return DeliveryResolver::normalizeLabel($value); }
function resolveAdresseLivraison(string $a, string $v, string $cp): ?array
{
    return DeliveryResolver::resolveAddress($a, $v, $cp);
}
function distanceKmDepuisCoordonnees(float $lat, float $lon): float
{
    return DeliveryResolver::distanceKmFromCoords($lat, $lon);
}

// ---------------------------------------------------------------------------
// Site config
// ---------------------------------------------------------------------------

function siteConfigValue(string $key, string|float|int $default): string
{
    return SiteConfig::get($key, $default);
}
function siteName(): string                                 { return SiteConfig::name(); }
function siteSlogan(): string                               { return SiteConfig::slogan(); }
function siteDomain(): string                               { return SiteConfig::domain(); }
function siteEmail(): string                                { return SiteConfig::email(); }
function sitePhone(): string                                { return SiteConfig::phone(); }
function siteAddress(): string                              { return SiteConfig::address(); }
function sitePostalCode(): string                           { return SiteConfig::postalCode(); }
function siteCity(): string                                 { return SiteConfig::city(); }
function siteFullAddress(): string                          { return SiteConfig::fullAddress(); }
function siteColor(string $key = 'couleur_principale'): string { return SiteConfig::color($key); }
function siteLat(): float                                   { return SiteConfig::lat(); }
function siteLng(): float                                   { return SiteConfig::lng(); }
function sitePostalCodesFree(): array                       { return SiteConfig::freePostalCodes(); }
function siteCityNormalized(): string
{
    return DeliveryResolver::normalizeLabel(SiteConfig::city() ?: 'bordeaux');
}
function livraisonBase(): float                             { return SiteConfig::deliveryBase(); }
function livraisonKm(): float                               { return SiteConfig::deliveryKm(); }
function reductionSeuilMontant(): float                     { return SiteConfig::discountThreshold(); }
function reductionTauxPourcentage(): float                  { return SiteConfig::discountRate(); }
function deliveryPricingLabel(): string                     { return SiteConfig::deliveryPricingLabel(); }

// ---------------------------------------------------------------------------
// Misc
// ---------------------------------------------------------------------------

function generateNumeroCommande(): string
{
    return 'VG-' . strtoupper(bin2hex(random_bytes(4))) . '-' . date('Ymd');
}

/**
 * Thin PDO wrapper for legacy code that hasn't been migrated to model classes yet.
 */
function db(): object
{
    static $wrapper = null;
    if ($wrapper === null) {
        $wrapper = new class {
            public function fetchAll(string $sql, array $params = []): array
            {
                $stmt = Database::getConnection()->prepare($sql);
                $stmt->execute($params);
                return $stmt->fetchAll();
            }
            public function fetchOne(string $sql, array $params = []): array|false
            {
                $stmt = Database::getConnection()->prepare($sql);
                $stmt->execute($params);
                return $stmt->fetch();
            }
            public function execute(string $sql, array $params = []): void
            {
                Database::getConnection()->prepare($sql)->execute($params);
            }
            public function lastInsertId(): string
            {
                return Database::getConnection()->lastInsertId();
            }
        };
    }
    return $wrapper;
}
