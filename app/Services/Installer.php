<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

/** Runs SQL migrations, seeds the three roles, and creates the company and its first admin. */
final class Installer
{
    public static function migrate(callable $out): void
    {
        DB::pdo()->exec('CREATE TABLE IF NOT EXISTS migrations (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            filename VARCHAR(190) NOT NULL UNIQUE,
            applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $done = array_column(DB::all('SELECT filename FROM migrations'), 'filename');
        $files = glob(BASE_PATH . '/database/migrations/*.sql') ?: [];
        sort($files);
        foreach ($files as $file) {
            $name = basename($file);
            if (in_array($name, $done, true)) {
                continue;
            }
            foreach (self::splitSql((string) file_get_contents($file)) as $stmt) {
                DB::pdo()->exec($stmt);
            }
            DB::insert('migrations', ['filename' => $name]);
            $out("  applied $name");
        }
    }

    /** Drop every table (development / tests only). */
    public static function dropAll(): void
    {
        DB::pdo()->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (DB::all('SHOW TABLES') as $row) {
            $t = (string) array_values($row)[0];
            DB::pdo()->exec('DROP TABLE IF EXISTS `' . str_replace('`', '', $t) . '`');
        }
        DB::pdo()->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    private static function splitSql(string $sql): array
    {
        $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? '';
        return array_filter(array_map('trim', explode(';', $sql)), fn($s) => $s !== '');
    }

    /** The three account types. code => [name, is_staff, description] */
    public const ROLES = [
        'homeowner'      => ['Homeowner', 0, 'Member of the public / client'],
        'admin'          => ['Admin', 1, 'Company office: full control'],
        'security_staff' => ['Security company', 1, 'Control room and response officers'],
    ];

    public static function seedRoles(callable $out): void
    {
        foreach (self::ROLES as $code => [$name, $staff, $desc]) {
            DB::run('INSERT INTO roles (code, name, is_staff, description) VALUES (?,?,?,?)
                     ON DUPLICATE KEY UPDATE name = VALUES(name), is_staff = VALUES(is_staff), description = VALUES(description)',
                [$code, $name, $staff, $desc]);
        }
        $out('  roles ready: homeowner, admin, security company');
        self::seedCategories($out);
    }

    /** Incident categories from the brief. code => [name, group, priority] */
    public const CATEGORIES = [
        'robbery' => ['Robbery', 'crime', 'high'], 'armed_robbery' => ['Armed robbery', 'crime', 'critical'],
        'hijacking' => ['Hijacking', 'crime', 'critical'], 'attempted_hijacking' => ['Attempted hijacking', 'crime', 'high'],
        'house_break_in' => ['House break-in', 'crime', 'high'], 'business_break_in' => ['Business break-in', 'crime', 'high'],
        'theft' => ['Theft', 'crime', 'normal'], 'vehicle_theft' => ['Vehicle theft', 'crime', 'high'],
        'vandalism' => ['Vandalism', 'crime', 'normal'], 'assault' => ['Assault', 'crime', 'high'],
        'suspicious_activity' => ['Suspicious activity', 'crime', 'normal'], 'trespassing' => ['Trespassing', 'crime', 'normal'],
        'kidnapping' => ['Kidnapping', 'crime', 'critical'], 'fraud' => ['Fraud', 'crime', 'low'],
        'other_crime' => ['Other criminal activity', 'crime', 'normal'],
        'mva' => ['Motor vehicle accident', 'emergency', 'high'], 'pedestrian_accident' => ['Pedestrian accident', 'emergency', 'high'],
        'fire' => ['Fire', 'emergency', 'critical'], 'medical' => ['Medical emergency', 'emergency', 'critical'],
        'workplace_accident' => ['Workplace accident', 'emergency', 'high'], 'road_hazard' => ['Road hazard', 'emergency', 'normal'],
        'other_emergency' => ['Other emergency', 'emergency', 'high'],
    ];

    public static function seedCategories(callable $out): void
    {
        if (!DB::value("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'incident_categories'")) {
            return;
        }
        $i = 0;
        foreach (self::CATEGORIES as $code => [$name, $group, $prio]) {
            DB::run('INSERT INTO incident_categories (code, name, group_code, priority, sort_order) VALUES (?,?,?,?,?)
                     ON DUPLICATE KEY UPDATE name = VALUES(name), group_code = VALUES(group_code), sort_order = VALUES(sort_order)',
                [$code, $name, $group, $prio, $i++]);
        }
        $out('  incident categories ready');
    }

    /** Starter services from the brief (quotation required); the Admin edits prices and availability. */
    public static function seedServices(int $tenantId, callable $out): void
    {
        if (DB::value('SELECT 1 FROM services WHERE tenant_id = ?', [$tenantId])) {
            return;
        }
        $list = [
            ['On-site guards', 'Guarding', 'Trained guards at your home, business or site, booked by the shift.', 'per shift'],
            ['Private event security', 'Events', 'Access control and guarding for private functions.', 'per event'],
            ['Home event protection', 'Events', 'Guards and parking control for parties and gatherings at home.', 'per event'],
            ['Personal security escort', 'Escorts', 'An officer accompanies you to and from a location.', 'per trip'],
            ['VIP escort', 'Escorts', 'Trained close-protection officers for VIP movements.', 'per hour'],
            ['Executive protection', 'Escorts', 'Executive close protection for business travel and events.', 'per day'],
        ];
        foreach ($list as $i => [$name, $cat, $desc, $unit]) {
            DB::insert('services', [
                'tenant_id' => $tenantId, 'name' => $name, 'category' => $cat, 'description' => $desc,
                'pricing' => 'quote', 'price_unit' => $unit, 'service_area' => area()['name'] . ' and surrounding areas',
                'availability_text' => 'Book at least 48 hours ahead', 'sort_order' => $i,
            ]);
        }
        $out('  once-off services added (quotation required; set prices in Admin → Sales → Services & prices)');
    }

    /** Create the company record from .env (only if none exists yet). */
    public static function seedCompany(callable $out): int
    {
        $existing = DB::value('SELECT id FROM tenants ORDER BY id LIMIT 1');
        if ($existing) {
            $out('  company already set up: ' . DB::value('SELECT display_name FROM tenant_settings WHERE tenant_id = ?', [$existing]));
            return (int) $existing;
        }
        $name = env('COMPANY_NAME', 'SENTRA 360 Security');
        $phone = env('COMPANY_PHONE', '');
        $email = env('COMPANY_EMAIL', '');
        $id = DB::insert('tenants', [
            'uuid' => uuid4(), 'slug' => 'company', 'name' => $name, 'status' => 'active',
            'contact_name' => $name, 'contact_email' => $email, 'contact_mobile' => $phone,
            'psira_number' => env('COMPANY_PSIRA'), 'approved_at' => now(),
        ]);
        DB::insert('tenant_settings', [
            'tenant_id' => $id, 'display_name' => $name,
            'primary_color' => env('COMPANY_PRIMARY_COLOR', '#0B3D91'),
            'secondary_color' => env('COMPANY_SECONDARY_COLOR', '#111827'),
            'welcome_message' => 'Keeping our community safe, together.',
            'contact_phone' => $phone ?: null, 'contact_email' => $email ?: null,
            'emergency_phone' => env('COMPANY_EMERGENCY_PHONE'),
            'notification_sender' => mb_substr($name, 0, 100),
        ]);
        $out("  company created: $name");
        return $id;
    }

    /** Create the first admin login from .env (only if no admin exists). */
    public static function seedAdmin(int $tenantId, callable $out): void
    {
        $roleId = (int) DB::value('SELECT id FROM roles WHERE code = "admin"');
        if (DB::value('SELECT 1 FROM users WHERE tenant_id = ? AND role_id = ?', [$tenantId, $roleId])) {
            $out('  admin account already exists');
            return;
        }
        $email = env('ADMIN_EMAIL', 'admin@company.local');
        $id = UserService::create($tenantId, $roleId, [
            'first_name' => 'Company', 'surname' => 'Admin', 'email' => $email,
            'mobile' => normalize_sa_mobile(env('ADMIN_MOBILE', '0820000000')) ?? '+27820000000',
            'password' => env('ADMIN_PASSWORD', 'ChangeMe!Admin360'),
        ], 'active');
        DB::run('UPDATE users SET email_verified_at = NOW(), mobile_verified_at = NOW() WHERE id = ?', [$id]);
        $out("  admin account created ($email)");
    }
}
