<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\DB;
use App\Core\HttpException;
use App\Core\TenantContext;

/**
 * DEVELOPMENT ONLY. Shows emails/SMS that were recorded but NOT delivered,
 * because no provider is configured. Route is not registered unless APP_ENV=development.
 */
final class DevController extends Controller
{
    public function outbox(): void
    {
        if (!is_dev()) {
            throw new HttpException(404);
        }
        $rows = TenantContext::current()
            ? DB::all('SELECT * FROM notifications WHERE tenant_id = ? ORDER BY id DESC LIMIT 50', [TenantContext::id()])
            : DB::all('SELECT * FROM notifications WHERE tenant_id IS NULL ORDER BY id DESC LIMIT 50');
        $this->view('dev/outbox', ['title' => 'Developer outbox', 'rows' => $rows], 'bare');
    }
}
