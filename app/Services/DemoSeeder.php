<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

/**
 * Sample accounts and branding for DEMONSTRATION environments only.
 * Refuses to run when APP_ENV=production.
 */
final class DemoSeeder
{
    public const PASSWORD = 'Demo!2026pass';

    public static function run(int $tenantId, callable $out): void
    {
        if (config('env') === 'production') {
            throw new \RuntimeException('Demo data cannot be loaded in production.');
        }
        DB::update('tenants', ['is_demo' => 1, 'psira_number' => 'DEMO-PSIRA'], ['id' => $tenantId]);
        DB::update('tenant_settings', [
            'welcome_message' => 'Protecting our community around the clock.',
            'about' => 'We are a PSIRA-registered private security provider serving residential and business clients. Join our community platform to stay informed, report incidents and apply for protection.',
            'services_offered' => "Armed response\nAlarm monitoring\nSecurity patrols\nCCTV installation\nElectric fencing\nSecurity assessments",
            'emergency_phone' => '0860 000 111',
            'emergency_notes' => 'In a life-threatening emergency, also call SAPS on 10111 or ambulance/fire on 10177.',
            'physical_address' => '12 Demo Street, Soshanguve, Pretoria',
        ], ['tenant_id' => $tenantId]);

        $accounts = [
            ['admin', 'Thabo', 'Mokoena', 'admin@demo.test', '0821000001', 'active'],
            ['security_staff', 'Control', 'Room', 'control@demo.test', '0821000002', 'active'],
            ['security_staff', 'Bongani', 'Zulu', 'officer@demo.test', '0821000003', 'active'],
            ['homeowner', 'Lerato', 'Khumalo', 'lerato@demo.test', '0831000001', 'active'],
            ['homeowner', 'Sipho', 'Nkosi', 'sipho@demo.test', '0831000002', 'active'],
            ['homeowner', 'Anika', 'Pillay', 'anika@demo.test', '0831000003', 'pending'],
        ];
        foreach ($accounts as [$role, $first, $last, $email, $mobile, $status]) {
            if (DB::value('SELECT 1 FROM users WHERE tenant_id = ? AND email = ?', [$tenantId, $email])) {
                continue;
            }
            $roleId = (int) DB::value('SELECT id FROM roles WHERE code = ?', [$role]);
            $uid = UserService::create($tenantId, $roleId, [
                'first_name' => $first, 'surname' => $last, 'email' => $email,
                'mobile' => normalize_sa_mobile($mobile), 'password' => self::PASSWORD,
            ], $status);
            if ($status === 'active') {
                DB::run('UPDATE users SET email_verified_at = NOW(), mobile_verified_at = NOW() WHERE id = ?', [$uid]);
            }
            if ($role === 'homeowner') {
                DB::insert('consents', ['tenant_id' => $tenantId, 'user_id' => $uid, 'consent_type' => 'terms_privacy', 'document_version' => config('policy_version'), 'granted' => 1]);
            }
            $out("  demo $role: $email");
        }
    }
}
