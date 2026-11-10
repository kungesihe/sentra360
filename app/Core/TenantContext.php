<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Single-company mode: the app serves exactly one security company.
 *
 * The company's record lives in the `tenants` table (one row). Every
 * company-owned row still carries tenant_id, so a second company can later be
 * served by copying the app with its own database and branding.
 */
final class TenantContext
{
    private static ?array $tenant = null;
    private static bool $loaded = false;

    public static function load(): ?array
    {
        if (!self::$loaded) {
            self::$tenant = DB::one(
                "SELECT t.*, s.display_name, s.logo_path, s.primary_color, s.secondary_color, s.welcome_message,
                        s.about, s.services_offered, s.contact_phone, s.contact_email AS public_email,
                        s.physical_address, s.emergency_phone, s.emergency_notes, s.terms_text, s.privacy_text, s.payment_instructions
                   FROM tenants t JOIN tenant_settings s ON s.tenant_id = t.id
                  ORDER BY t.id LIMIT 1"
            );
            self::$loaded = true;
        }
        return self::$tenant;
    }

    public static function current(): ?array
    {
        return self::load();
    }

    public static function id(): int
    {
        $t = self::load();
        if ($t === null) {
            throw new HttpException(503, 'The company has not been set up yet. Run bin/install.php.');
        }
        return (int) $t['id'];
    }

    /** Forget the cached company (after branding changes, and in tests). */
    public static function refresh(): void
    {
        self::$loaded = false;
        self::$tenant = null;
    }
}
