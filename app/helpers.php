<?php
declare(strict_types=1);

use App\Core\Csrf;
use App\Core\Session;
use App\Core\TenantContext;

function env(string $key, ?string $default = null): ?string
{
    $v = getenv($key);
    return ($v === false || $v === '') ? $default : $v;
}

function config(string $key): mixed
{
    static $c = null;
    if ($c === null) {
        $c = [
            'name'          => env('APP_NAME', 'SENTRA 360'),
            'tagline'       => env('APP_TAGLINE', 'Your security company. Your brand. One connected security platform.'),
            'env'           => env('APP_ENV', 'production'),
            // APP_URL, or on Render the address Render assigns (RENDER_EXTERNAL_URL).
            'url'           => rtrim(env('APP_URL') ?? env('RENDER_EXTERNAL_URL') ?? 'http://localhost', '/'),
            'key'           => env('APP_KEY', ''),
            'mail_provider' => env('MAIL_PROVIDER'),
            'sms_provider'  => env('SMS_PROVIDER'),
            'mail_from'     => env('MAIL_FROM', 'no-reply@example.com'),
            'policy_version'=> '2026-11',
            // Mobile/email verification for homeowners. Off until SMS/email providers are connected.
            'require_verification' => env('REQUIRE_VERIFICATION', '0') === '1',
            'vat_rate'      => 0.15,
        ];
    }
    return $c[$key] ?? null;
}

function is_dev(): bool
{
    return config('env') === 'development';
}

/** HTML-escape for output. */
function e(mixed $v): string
{
    return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(Csrf::token()) . '">';
}

/** Absolute URL to a page of this app (used in emails and SMS). */
function app_url(string $path = '/'): string
{
    return config('url') . $path;
}

function redirect(string $to, int $code = 302): never
{
    header('Location: ' . $to, true, $code);
    exit;
}

function flash(string $type, string $message): void
{
    Session::flash($type, $message);
}

/** Old form input after a validation failure. */
function old(string $key, string $default = ''): string
{
    $old = $_SESSION['_old'] ?? [];
    return e($old[$key] ?? $default);
}

function money(int $cents): string
{
    return 'R' . number_format($cents / 100, 0, '.', ' ');
}

/** Rand amount with cents, e.g. R1 250.00 */
function money_exact(int $cents): string
{
    return 'R' . number_format($cents / 100, 2, '.', ' ');
}

/**
 * Simplified navigation: a few main sections per area, each with tabs.
 * Returns [ [href, icon, label, [[tabHref, tabLabel], ...]], ... ].
 */
function nav_groups(string $portal): array
{
    return match ($portal) {
        'homeowner' => [
            ['/account', 'house', 'Home', []],
            ['/account/incidents', 'megaphone', 'Reports', [['/account/incidents', 'My reports'], ['/account/incidents/new', 'Report an incident']]],
            ['/account/assets/property', 'shield-check', 'Protection', [['/account/assets/property', 'Home & business'], ['/account/assets/vehicle', 'Vehicles'], ['/account/applications', 'Plan requests']]],
            ['/account/services', 'bag-check', 'Services', [['/account/services', 'Browse'], ['/account/pricelist', 'Price list'], ['/account/requests', 'My bookings'], ['/account/quotations', 'Quotations'], ['/account/payments', 'Payments']]],
            ['/account/profile', 'person', 'Account', [['/account/profile', 'Profile'], ['/account/contacts', 'Emergency contacts'], ['/account/notifications', 'Notifications']]],
        ],
        'admin' => [
            ['/admin', 'speedometer2', 'Dashboard', []],
            ['/admin/incidents', 'megaphone', 'Incidents', [['/admin/incidents', 'Incidents'], ['/admin/patrols', 'Patrol log'], ['/admin/alerts', 'Safety alerts']]],
            ['/admin/applications', 'people', 'Clients', [['/admin/applications', 'Applications'], ['/admin/leads', 'Leads'], ['/admin/homeowners', 'Homeowners']]],
            ['/admin/quotations', 'cash-coin', 'Sales', [['/admin/quotations', 'Quotations'], ['/admin/requests', 'Bookings'], ['/admin/pricelist', 'Services & prices'], ['/admin/payments', 'Payments']]],
            ['/admin/reports', 'bar-chart', 'Reports', []],
            ['/admin/staff', 'gear', 'Settings', [['/admin/staff', 'Logins'], ['/admin/branding', 'Branding'], ['/admin/audit', 'Audit log'], ['/admin/profile', 'My login']]],
        ],
        'security' => [
            ['/security', 'display', 'Control room', []],
            ['/security/assignments', 'person-walking', 'Incidents', [['/security/assignments', 'My assignments'], ['/security/incidents', 'All incidents']]],
            ['/security/patrols', 'signpost-split', 'Patrols', []],
            ['/security/homeowners', 'house-door', 'Homeowners', []],
        ],
        default => [],
    };
}

/** Index of the nav group the path belongs to (longest matching prefix wins). */
function nav_active(array $groups, string $path): ?int
{
    $best = null;
    $bestLen = -1;
    foreach ($groups as $i => [$href, , , $tabs]) {
        foreach (array_merge([$href], array_column($tabs, 0)) as $p) {
            // Section homes (/account, /admin, /security) only match exactly; others also match their sub-pages.
            $match = $path === $p || (!in_array($p, ['/account', '/admin', '/security'], true) && str_starts_with($path, $p . '/'));
            if ($match && strlen($p) > $bestLen) {
                $best = $i;
                $bestLen = strlen($p);
            }
        }
    }
    return $best;
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function uuid4(): string
{
    $d = random_bytes(16);
    $d[6] = chr((ord($d[6]) & 0x0f) | 0x40);
    $d[8] = chr((ord($d[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d), 4));
}

/** Keyed hash for OTPs / tokens stored in the database. */
function secret_hash(string $value): string
{
    return hash_hmac('sha256', $value, (string) config('key'));
}

/** Normalise a South African mobile number to +27XXXXXXXXX or return null. */
function normalize_sa_mobile(string $raw): ?string
{
    $d = preg_replace('/[^\d+]/', '', $raw) ?? '';
    if (str_starts_with($d, '+27')) {
        $d = substr($d, 3);
    } elseif (str_starts_with($d, '27') && strlen($d) === 11) {
        $d = substr($d, 2);
    } elseif (str_starts_with($d, '0')) {
        $d = substr($d, 1);
    }
    return preg_match('/^[6-8]\d{8}$/', $d) ? '+27' . $d : null;
}

function mask_mobile(string $m): string
{
    return substr($m, 0, 5) . str_repeat('•', max(0, strlen($m) - 7)) . substr($m, -2);
}

function mask_email(string $email): string
{
    [$u, $d] = array_pad(explode('@', $email, 2), 2, '');
    return substr($u, 0, 2) . str_repeat('•', max(1, strlen($u) - 2)) . '@' . $d;
}

function tenant(): ?array
{
    return TenantContext::current();
}

/**
 * Visitor IP. Behind a hosting proxy (Render, Heroku, a load balancer) set
 * TRUST_PROXY=1 so the real address from X-Forwarded-For is used; otherwise
 * that header is ignored because visitors could fake it.
 */
function client_ip(): string
{
    if (env('TRUST_PROXY') === '1' && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $first = trim(explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
        if (filter_var($first, FILTER_VALIDATE_IP)) {
            return $first;
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function is_https(): bool
{
    return (($_SERVER['HTTPS'] ?? '') === 'on')
        || (env('TRUST_PROXY') === '1' && ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

function status_badge(string $status): string
{
    $map = [
        'active' => 'success', 'trial' => 'info', 'pending' => 'warning', 'suspended' => 'danger',
        'rejected' => 'secondary', 'cancelled' => 'secondary', 'disabled' => 'secondary',
        'not_configured' => 'warning', 'sent' => 'success', 'failed' => 'danger', 'queued' => 'info',
        'new' => 'primary', 'community' => 'secondary', 'client' => 'success', 'expired' => 'secondary',
    ];
    $cls = $map[$status] ?? 'secondary';
    return '<span class="badge text-bg-' . $cls . '">' . e(str_replace('_', ' ', $status)) . '</span>';
}

/**
 * The company's home area: used for the map's starting view, address defaults and wording.
 * Set COMPANY_AREA / COMPANY_CITY / MAP_CENTER in .env to move the app to another area.
 */
function area(): array
{
    static $a = null;
    if ($a === null) {
        $name = env('COMPANY_AREA', 'Soshanguve');
        [$lat, $lng] = array_map('floatval', explode(',', (string) env('MAP_CENTER', '-25.5230,28.0990')) + [1 => '28.0990']);
        $a = [
            'name' => $name,
            'city' => env('COMPANY_CITY', 'Pretoria'),
            'suburb_example' => env('COMPANY_SUBURB_EXAMPLE', $name . ' Block H'),
            'lat' => $lat, 'lng' => $lng,
        ];
    }
    return $a;
}

/** A South African phone number as WhatsApp wants it (digits only, 27 country code), or null. */
function wa_number(?string $phone): ?string
{
    $d = preg_replace('/\D+/', '', (string) $phone);
    if ($d === '' || strlen($d) < 9) {
        return null;
    }
    if (str_starts_with($d, '0')) {
        $d = '27' . substr($d, 1);
    }
    return strlen($d) >= 11 ? $d : null;
}
