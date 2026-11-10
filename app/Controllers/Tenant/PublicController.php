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

    public function privacy(): void
    {
        $this->view('tenant/policy', ['title' => 'Privacy policy', 'kind' => 'privacy'], 'tenant');
    }

    public function terms(): void
    {
        $this->view('tenant/policy', ['title' => 'Terms and conditions', 'kind' => 'terms'], 'tenant');
    }
}
