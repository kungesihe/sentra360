<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

final class UserService
{
    public static function create(int $tenantId, int $roleId, array $d, string $status = 'pending'): int
    {
        $userId = DB::insert('users', [
            'uuid'          => uuid4(),
            'tenant_id'     => $tenantId,
            'role_id'       => $roleId,
            'email'         => strtolower($d['email']),
            'mobile'        => $d['mobile'],
            'password_hash' => password_hash($d['password'], PASSWORD_DEFAULT),
            'status'        => $status,
        ]);
        DB::insert('user_profiles', [
            'user_id'    => $userId,
            'tenant_id'  => $tenantId,
            'first_name' => $d['first_name'],
            'surname'    => $d['surname'],
        ]);
        return $userId;
    }

    public static function emailTaken(int $tenantId, string $email): bool
    {
        return (bool) DB::value('SELECT 1 FROM users WHERE tenant_id = ? AND email = ?', [$tenantId, strtolower($email)]);
    }

    public static function mobileTaken(int $tenantId, string $mobile): bool
    {
        return (bool) DB::value('SELECT 1 FROM users WHERE tenant_id = ? AND mobile = ?', [$tenantId, $mobile]);
    }

    public static function find(int $tenantId, int $userId): ?array
    {
        return DB::one(
            'SELECT u.*, r.code AS role_code, r.name AS role_name, r.is_staff, p.first_name, p.surname
               FROM users u JOIN roles r ON r.id = u.role_id LEFT JOIN user_profiles p ON p.user_id = u.id
              WHERE u.id = ? AND u.tenant_id = ?',
            [$userId, $tenantId]
        );
    }

    /** A pending account becomes active once both mobile and email are verified. */
    public static function refreshStatus(int $userId): void
    {
        DB::run(
            'UPDATE users SET status = "active"
              WHERE id = ? AND status = "pending" AND email_verified_at IS NOT NULL AND mobile_verified_at IS NOT NULL',
            [$userId]
        );
    }
}
