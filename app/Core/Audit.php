<?php
declare(strict_types=1);

namespace App\Core;

final class Audit
{
    public static function log(
        string $action,
        ?string $entityType = null,
        int|string|null $entityId = null,
        array $details = [],
        ?int $tenantId = null,
        ?string $actorType = null,
        ?int $actorId = null
    ): void {
        if ($actorType === null) {
            $u = (PHP_SAPI !== 'cli' && TenantContext::current()) ? Auth::user() : null;
            if ($u) {
                $actorType = 'user';
                $actorId = (int) $u['id'];
            } else {
                $actorType = PHP_SAPI === 'cli' ? 'system' : 'guest';
            }
        }
        if ($tenantId === null && PHP_SAPI !== 'cli' && TenantContext::current()) {
            $tenantId = TenantContext::id();
        }
        DB::insert('audit_logs', [
            'tenant_id'   => $tenantId,
            'actor_type'  => $actorType,
            'actor_id'    => $actorId,
            'action'      => $action,
            'entity_type' => $entityType,
            'entity_id'   => $entityId === null ? null : (string) $entityId,
            'details'     => $details ? json_encode($details, JSON_UNESCAPED_SLASHES) : null,
            'ip_address'  => PHP_SAPI === 'cli' ? null : client_ip(),
            'user_agent'  => PHP_SAPI === 'cli' ? null : substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
        ]);
    }
}
