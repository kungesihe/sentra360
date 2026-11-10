<?php
declare(strict_types=1);

namespace App\Controllers\Tenant;

use App\Controllers\Controller;
use App\Core\TenantContext;

/** The company's public website. */
final class PublicController extends Controller
{
    public function landing(): void
    {
        $t = TenantContext::current();
        $services = array_values(array_filter(array_map('trim', explode("\n", (string) $t['services_offered']))));
        $this->view('tenant/landing', ['title' => $t['display_name'], 'services' => $services], 'tenant');
    }

    /** The company logo, stored in the database. */
    public function logo(): void
    {
        $row = \App\Core\DB::one('SELECT logo_data, logo_mime FROM tenant_settings WHERE tenant_id = ?', [TenantContext::id()]);
        if (!$row || $row['logo_data'] === null || !in_array($row['logo_mime'], ['image/png', 'image/jpeg', 'image/webp'], true)) {
            throw new \App\Core\HttpException(404);
        }
        header('Content-Type: ' . $row['logo_mime']);
        header('Cache-Control: public, max-age=31536000, immutable'); // URL changes whenever the logo changes
        header("Content-Security-Policy: default-src 'none'");
        echo $row['logo_data'];
    }

    /** Web app manifest: makes the site installable and is used to build the Android (Play Store) app. */
    public function manifest(): void
    {
        $t = TenantContext::current();
        $color = preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $t['secondary_color']) ? $t['secondary_color'] : '#111827';
        header('Content-Type: application/manifest+json');
        echo json_encode([
            'id' => '/',
            'name' => $t['display_name'],
            'short_name' => mb_substr($t['display_name'], 0, 12),
            'description' => 'Report incidents, request protection and services, and reach the control room with a panic button.',
            'start_url' => '/account?source=app',
            'scope' => '/',
            'display' => 'standalone',
            'orientation' => 'portrait',
            'background_color' => '#ffffff',
            'theme_color' => $color,
            'lang' => 'en-ZA',
            'categories' => ['lifestyle', 'utilities'],
            'icons' => [
                ['src' => '/assets/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/assets/icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/assets/icons/maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
            'shortcuts' => [
                ['name' => 'Panic button', 'url' => '/account/panic', 'icons' => [['src' => '/assets/icons/icon-192.png', 'sizes' => '192x192']]],
                ['name' => 'Report an incident', 'url' => '/account/incidents/new', 'icons' => [['src' => '/assets/icons/icon-192.png', 'sizes' => '192x192']]],
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    /**
     * Digital Asset Links: proves to Android that the Play Store app owns this site,
     * so it opens full-screen (Trusted Web Activity). Set TWA_PACKAGE_NAME and
     * TWA_SHA256_FINGERPRINTS (comma-separated) after generating the Android app.
     */
    public function assetLinks(): void
    {
        $pkg = env('TWA_PACKAGE_NAME');
        $prints = array_values(array_filter(array_map('trim', explode(',', (string) env('TWA_SHA256_FINGERPRINTS', '')))));
        header('Content-Type: application/json');
        if (!$pkg || !$prints) {
            echo '[]';
            return;
        }
        echo json_encode([[
            'relation' => ['delegate_permission/common.handle_all_urls'],
            'target' => ['namespace' => 'android_app', 'package_name' => $pkg, 'sha256_cert_fingerprints' => $prints],
        ]], JSON_UNESCAPED_SLASHES);
    }

    public function privacy(): void
    {
        $this->view('tenant/policy', ['title' => 'Privacy policy', 'kind' => 'privacy'], 'tenant');
    }

    public function terms(): void
    {
        $this->view('tenant/policy', ['title' => 'Terms and conditions', 'kind' => 'terms'], 'tenant');
    }
}
