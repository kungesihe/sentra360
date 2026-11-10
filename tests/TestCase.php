<?php
declare(strict_types=1);

namespace Tests;

use App\Core\DB;
use App\Services\DemoSeeder;
use App\Services\UserService;

final class AssertionFailed extends \Exception
{
}

/** Assertions and fixtures shared by all test files. */
final class T
{
    public static array $tests = [];
    public static int $companyId = 0;
    public const HOST = 'localhost';

    /** login path per role */
    public const LOGIN = ['homeowner' => '/login', 'admin' => '/login', 'security_staff' => '/login'];

    public static function test(string $name, callable $fn): void
    {
        self::$tests[] = [$name, $fn];
    }

    public static function true(mixed $cond, string $msg): void
    {
        if (!$cond) {
            throw new AssertionFailed($msg);
        }
    }

    public static function eq(mixed $expected, mixed $actual, string $msg): void
    {
        if ($expected !== $actual) {
            throw new AssertionFailed($msg . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')');
        }
    }

    public static function contains(string $needle, string $haystack, string $msg): void
    {
        self::true(str_contains($haystack, $needle), $msg . " (missing: $needle)");
    }

    public static function notContains(string $needle, string $haystack, string $msg): void
    {
        self::true(!str_contains($haystack, $needle), $msg . " (should not contain: $needle)");
    }

    public static function redirectsTo(string $path, Http $c, string $msg): void
    {
        self::true(in_array($c->status, [301, 302, 303], true), "$msg (expected redirect, got {$c->status})");
        $loc = parse_url($c->location(), PHP_URL_PATH) ?: $c->location();
        self::eq($path, $loc, "$msg (redirect target)");
    }

    // ---------------------------------------------------------------- fixtures

    /** Create an account of the given type in the test company. */
    public static function user(string $role = 'homeowner', string $status = 'active', ?string $email = null): array
    {
        $roleId = (int) DB::value('SELECT id FROM roles WHERE code = ?', [$role]);
        $email ??= $role . '.' . bin2hex(random_bytes(4)) . '@example.test';
        $id = UserService::create(self::$companyId, $roleId, [
            'first_name' => 'Test', 'surname' => ucfirst($role), 'email' => $email, 'mobile' => self::mobile(), 'password' => DemoSeeder::PASSWORD,
        ], $status);
        return UserService::find(self::$companyId, $id);
    }

    /** Give a homeowner a complete profile (home address + emergency contact) so the panic button unlocks. */
    public static function completeProfile(array $user): void
    {
        DB::run('INSERT INTO registered_properties (tenant_id, owner_id, name, property_type, address, suburb, city) VALUES (?, ?, "Home", "House", "1 Test St", "Rivonia", "Johannesburg")', [self::$companyId, $user['id']]);
        DB::run('INSERT INTO emergency_contacts (tenant_id, user_id, name, relationship, mobile) VALUES (?, ?, "Test Contact", "Sister", ?)', [self::$companyId, $user['id'], self::mobile()]);
    }

    public static function mobile(): string
    {
        return '+2782' . str_pad((string) random_int(0, 9999999), 7, '0', STR_PAD_LEFT);
    }

    /** Sign in on the login page that matches the account's role. */
    public static function login(array $user, string $password = DemoSeeder::PASSWORD): Http
    {
        $c = new Http(self::HOST);
        $path = self::LOGIN[$user['role_code']];
        $c->get($path);
        $c->post($path, ['identifier' => $user['email'], 'password' => $password]);
        self::true($c->status === 302 && !str_contains($c->location(), 'login'), "sign-in for {$user['email']} should succeed (got {$c->status} → {$c->location()})");
        return $c;
    }

    /** Latest undelivered message body for a recipient. */
    public static function lastMessage(string $recipient): string
    {
        return (string) DB::value('SELECT body FROM notifications WHERE recipient = ? ORDER BY id DESC LIMIT 1', [$recipient]);
    }
}
