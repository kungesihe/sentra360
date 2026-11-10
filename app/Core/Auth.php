<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Authentication for the three kinds of account:
 *
 *   portal      role code        signs in at        lands on
 *   homeowner   homeowner        /login             /account
 *   admin       admin            /admin/login       /admin
 *   security    security_staff   /security/login    /security
 *
 * An account can only sign in through its own portal's login page and only
 * open its own portal's pages.
 *
 * - Passwords: password_hash()/password_verify()
 * - Lockout: MAX_FAILS failed attempts locks the account for LOCK_MINUTES
 * - Sessions: server-side record per sign-in (user_sessions), revocable,
 *   idle timeout and absolute lifetime.
 */
final class Auth
{
    public const MAX_FAILS = 5;
    public const LOCK_MINUTES = 15;
    public const IDLE_SECONDS = 1800;      // 30 minutes
    public const ABSOLUTE_SECONDS = 43200; // 12 hours

    /** portal => [role code, login path, home path, label] */
    public const PORTALS = [
        'homeowner' => ['homeowner', '/login', '/account', 'Homeowner'],
        'admin'     => ['admin', '/admin/login', '/admin', 'Admin'],
        'security'  => ['security_staff', '/security/login', '/security', 'Security company'],
    ];

    private static array|false|null $user = null;

    public static function portalForRole(string $roleCode): ?string
    {
        foreach (self::PORTALS as $portal => [$role]) {
            if ($role === $roleCode) {
                return $portal;
            }
        }
        return null;
    }

    public static function portalForPath(string $path): string
    {
        foreach (self::PORTALS as $portal => [, $login]) {
            if ($path === $login) {
                return $portal;
            }
        }
        return 'homeowner';
    }

    // ------------------------------------------------------------------ login

    /** @return array{ok:bool, error?:string, user?:array} */
    public static function attempt(string $portal, string $identifier, string $password): array
    {
        $tenantId = TenantContext::id();
        $identifier = trim($identifier);
        $mobile = normalize_sa_mobile($identifier);
        $user = DB::one(
            'SELECT u.*, r.code AS role_code FROM users u JOIN roles r ON r.id = u.role_id
              WHERE u.tenant_id = ? AND (u.email = ? OR u.mobile = ?) LIMIT 1',
            [$tenantId, strtolower($identifier), $mobile ?? '-']
        );
        $generic = 'Incorrect login details.';
        if ($user === null) {
            // Spend comparable time so response timing does not reveal whether the account exists.
            password_verify($password, '$2y$10$abcdefghijklmnopqrstuuSbV2r9k3s6b0p6CzS8fFq2sQ7Qb4O9e');
            return ['ok' => false, 'error' => $generic];
        }
        if ($user['locked_until'] !== null && strtotime($user['locked_until']) > time()) {
            return ['ok' => false, 'error' => 'This account is temporarily locked after too many failed attempts. Try again after ' . date('H:i', strtotime($user['locked_until'])) . ', or reset your password.'];
        }
        if (!password_verify($password, $user['password_hash'])) {
            $fails = (int) $user['failed_login_count'] + 1;
            $lock = $fails >= self::MAX_FAILS ? date('Y-m-d H:i:s', time() + self::LOCK_MINUTES * 60) : null;
            DB::update('users', ['failed_login_count' => $lock ? 0 : $fails, 'locked_until' => $lock], ['id' => $user['id']]);
            if ($lock) {
                Audit::log('auth.locked', 'user', $user['id'], ['fails' => $fails], $tenantId, 'guest');
                return ['ok' => false, 'error' => 'Too many failed attempts. This account is locked for ' . self::LOCK_MINUTES . ' minutes.'];
            }
            return ['ok' => false, 'error' => $generic];
        }
        // Correct password, but this account belongs to a different login page.
        $own = self::portalForRole($user['role_code']);
        if ($own !== $portal) {
            Audit::log('auth.wrong_portal', 'user', $user['id'], ['tried' => $portal], $tenantId, 'guest');
            $label = $own ? self::PORTALS[$own][3] : 'another';
            $login = $own ? self::PORTALS[$own][1] : '/login';
            return ['ok' => false, 'error' => "This is a $label account. Please sign in on the $label login page.", 'redirect' => $login];
        }
        if (in_array($user['status'], ['suspended', 'disabled'], true)) {
            return ['ok' => false, 'error' => 'This account is not active. Please contact the company.'];
        }
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            DB::update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], ['id' => $user['id']]);
        }
        DB::update('users', ['failed_login_count' => 0, 'locked_until' => null, 'last_login_at' => now()], ['id' => $user['id']]);
        return ['ok' => true, 'user' => $user];
    }

    public static function startSession(int $userId, int $tenantId): void
    {
        Session::regenerate();
        $token = bin2hex(random_bytes(32));
        DB::insert('user_sessions', [
            'token_hash' => hash('sha256', $token),
            'user_type'  => 'user',
            'user_id'    => $userId,
            'tenant_id'  => $tenantId,
            'ip_address' => client_ip(),
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
        ]);
        $_SESSION['auth'] = ['id' => $userId, 'tenant_id' => $tenantId, 'token' => $token, 'started' => time(), 'last' => time()];
        self::$user = null;
    }

    public static function logout(): void
    {
        if (!empty($_SESSION['auth']['token'])) {
            DB::run('UPDATE user_sessions SET revoked_at = NOW() WHERE token_hash = ? AND revoked_at IS NULL', [hash('sha256', $_SESSION['auth']['token'])]);
        }
        Session::destroy();
        self::$user = false;
    }

    public static function revokeAll(int $userId): void
    {
        DB::run('UPDATE user_sessions SET revoked_at = NOW() WHERE user_id = ? AND revoked_at IS NULL', [$userId]);
    }

    // ---------------------------------------------------------------- current

    public static function user(): ?array
    {
        if (self::$user !== null) {
            return self::$user ?: null;
        }
        self::$user = false;
        $a = $_SESSION['auth'] ?? null;
        if (!$a) {
            return null;
        }
        $now = time();
        if ($now - $a['last'] > self::IDLE_SECONDS || $now - $a['started'] > self::ABSOLUTE_SECONDS) {
            self::logout();
            Session::start();
            Session::flash('warning', 'You were signed out because your session expired.');
            return null;
        }
        if ($a['tenant_id'] !== TenantContext::id()) {
            unset($_SESSION['auth']);
            return null;
        }
        $rec = DB::one('SELECT id, last_seen_at, revoked_at FROM user_sessions WHERE token_hash = ?', [hash('sha256', $a['token'])]);
        if (!$rec || $rec['revoked_at'] !== null) {
            unset($_SESSION['auth']);
            return null;
        }
        if ($now - strtotime($rec['last_seen_at']) > 60) {
            DB::run('UPDATE user_sessions SET last_seen_at = NOW() WHERE id = ?', [$rec['id']]);
        }
        $_SESSION['auth']['last'] = $now;
        $u = DB::one(
            'SELECT u.*, r.code AS role_code, r.name AS role_name, r.is_staff, p.first_name, p.surname
               FROM users u JOIN roles r ON r.id = u.role_id
               LEFT JOIN user_profiles p ON p.user_id = u.id
              WHERE u.id = ? AND u.tenant_id = ?',
            [$a['id'], $a['tenant_id']]
        );
        if ($u && !in_array($u['status'], ['suspended', 'disabled'], true)) {
            $u['portal'] = self::portalForRole($u['role_code']);
            self::$user = $u;
        }
        return self::$user ?: null;
    }

    /** Reset per-request caches (used by tests). */
    public static function reset(): void
    {
        self::$user = null;
    }

    // ------------------------------------------------------------ middleware

    /** Only accounts of this portal's type may open its pages. */
    public static function requirePortal(string $portal): \Closure
    {
        return function () use ($portal): void {
            $u = self::user();
            if (!$u) {
                $_SESSION['_intended'] = $_SERVER['REQUEST_URI'] ?? self::PORTALS[$portal][2];
                redirect(self::PORTALS[$portal][1]);
            }
            if ($u['portal'] !== $portal) {
                Audit::log('access.denied', 'portal', $portal);
                throw new HttpException(403);
            }
        };
    }

    public static function validatePassword(string $pw): ?string
    {
        if (strlen($pw) < 10) {
            return 'Password must be at least 10 characters.';
        }
        if (!preg_match('/[A-Za-z]/', $pw) || !preg_match('/\d/', $pw)) {
            return 'Password must contain letters and at least one number.';
        }
        if (strlen($pw) > 128) {
            return 'Password is too long.';
        }
        return null;
    }
}
