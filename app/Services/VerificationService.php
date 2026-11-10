<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\Notifier;

/**
 * Mobile OTPs, email verification links and password-reset links.
 * Only keyed hashes are stored; codes/tokens are single-use and expire.
 */
final class VerificationService
{
    public const OTP_TTL = 600;          // 10 minutes
    public const EMAIL_TTL = 86400;      // 24 hours
    public const RESET_TTL = 3600;       // 1 hour
    public const MAX_OTP_ATTEMPTS = 5;

    private static function store(array $user, string $purpose, string $secret, int $ttl): void
    {
        DB::run(
            'UPDATE verification_codes SET consumed_at = NOW() WHERE user_id = ? AND purpose = ? AND consumed_at IS NULL',
            [$user['id'], $purpose]
        );
        DB::insert('verification_codes', [
            'tenant_id'  => $user['tenant_id'],
            'user_id'    => $user['id'],
            'purpose'    => $purpose,
            'code_hash'  => secret_hash($purpose . '|' . $secret),
            'expires_at' => date('Y-m-d H:i:s', time() + $ttl),
        ]);
    }

    /** @return string delivery status (Notifier::DELIVERED / NOT_CONFIGURED / failed) */
    public static function sendMobileOtp(array $user, array $tenant): string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        self::store($user, 'verify_mobile', $code, self::OTP_TTL);
        $msg = "{$tenant['display_name']}: your verification code is $code. It expires in 10 minutes. Never share this code.";
        return Notifier::sms($user['mobile'], $msg, (int) $user['tenant_id'], (int) $user['id']);
    }

    public static function sendEmailVerification(array $user, array $tenant): string
    {
        $token = bin2hex(random_bytes(32));
        self::store($user, 'verify_email', $token, self::EMAIL_TTL);
        $link = app_url('/verify-email?u=' . urlencode($user['uuid']) . '&t=' . $token);
        $body = "Hello,\n\nPlease confirm your email address for your {$tenant['display_name']} account by opening this link:\n\n$link\n\nThe link expires in 24 hours. If you did not create this account, you can ignore this email.";
        return Notifier::email($user['email'], 'Confirm your email address', $body, (int) $user['tenant_id'], (int) $user['id']);
    }

    public static function sendPasswordReset(array $user, array $tenant): string
    {
        $token = bin2hex(random_bytes(32));
        self::store($user, 'password_reset', $token, self::RESET_TTL);
        $link = app_url('/reset-password?u=' . urlencode($user['uuid']) . '&t=' . $token);
        $body = "Hello,\n\nWe received a request to reset the password for your {$tenant['display_name']} account.\n\nReset it here (valid for 1 hour):\n$link\n\nIf you did not ask for this, ignore this email â€” your password will not change.";
        return Notifier::email($user['email'], 'Reset your password', $body, (int) $user['tenant_id'], (int) $user['id']);
    }

    /** @return true|string true on success, otherwise an error message */
    public static function checkOtp(array $user, string $code): bool|string
    {
        $row = DB::one(
            'SELECT * FROM verification_codes WHERE user_id = ? AND purpose = "verify_mobile" AND consumed_at IS NULL
              ORDER BY id DESC LIMIT 1',
            [$user['id']]
        );
        if (!$row || strtotime($row['expires_at']) < time()) {
            return 'That code has expired. Request a new one.';
        }
        if ((int) $row['attempts'] >= self::MAX_OTP_ATTEMPTS) {
            return 'Too many incorrect codes. Request a new one.';
        }
        if (!hash_equals($row['code_hash'], secret_hash('verify_mobile|' . trim($code)))) {
            DB::run('UPDATE verification_codes SET attempts = attempts + 1 WHERE id = ?', [$row['id']]);
            return 'That code is incorrect.';
        }
        DB::run('UPDATE verification_codes SET consumed_at = NOW() WHERE id = ?', [$row['id']]);
        return true;
    }

    /** Validate (and optionally consume) an email or reset token. Returns the user row or null. */
    public static function checkToken(int $tenantId, string $uuid, string $token, string $purpose, bool $consume): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }
        $user = DB::one('SELECT * FROM users WHERE uuid = ? AND tenant_id = ?', [$uuid, $tenantId]);
        if (!$user) {
            return null;
        }
        $row = DB::one(
            'SELECT * FROM verification_codes WHERE user_id = ? AND tenant_id = ? AND purpose = ? AND consumed_at IS NULL
              ORDER BY id DESC LIMIT 1',
            [$user['id'], $tenantId, $purpose]
        );
        if (!$row || strtotime($row['expires_at']) < time() || !hash_equals($row['code_hash'], secret_hash($purpose . '|' . $token))) {
            return null;
        }
        if ($consume) {
            DB::run('UPDATE verification_codes SET consumed_at = NOW() WHERE id = ?', [$row['id']]);
        }
        return $user;
    }
}
