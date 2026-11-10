<?php
declare(strict_types=1);

namespace App\Controllers\Tenant;

use App\Controllers\Controller;
use App\Core\Audit;
use App\Core\Auth;
use App\Core\DB;
use App\Core\HttpException;
use App\Core\TenantContext;

/**
 * Serves uploaded files with access control:
 * staff (admin/security) see company files; homeowners see only their own;
 * service photos are public.
 */
final class FilesController extends Controller
{
    public function show(string $id): void
    {
        $f = DB::one('SELECT * FROM files WHERE id = ? AND tenant_id = ?', [(int) $id, TenantContext::id()]) ?? throw new HttpException(404);
        $u = Auth::user();
        $allowed = $f['owner_type'] === 'service';
        if (!$allowed && $u) {
            if ($u['is_staff']) {
                $allowed = true;
            } else {
                $ownerCol = ['incident' => ['incidents', 'reporter_id'], 'property' => ['registered_properties', 'owner_id'], 'vehicle' => ['registered_vehicles', 'owner_id'], 'payment' => ['payments', 'customer_id']][$f['owner_type']] ?? null;
                if ($ownerCol) {
                    [$table, $col] = $ownerCol;
                    $allowed = (int) DB::value("SELECT `$col` FROM `$table` WHERE id = ? AND tenant_id = ?", [$f['owner_id'], TenantContext::id()]) === (int) $u['id'];
                }
            }
        }
        if (!$allowed) {
            throw new HttpException($u ? 403 : 404);
        }
        if ($u && $u['is_staff'] && $f['owner_type'] !== 'service') {
            Audit::log('file.viewed', 'file', $f['id']);
        }
        $inline = str_starts_with($f['mime'], 'image/') || str_starts_with($f['mime'], 'video/') || $f['mime'] === 'application/pdf';
        header('Content-Type: ' . $f['mime']);
        header('Content-Length: ' . strlen($f['data']));
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', $f['original_name']) . '"');
        header("Content-Security-Policy: default-src 'none'; img-src 'self'; media-src 'self'; style-src 'unsafe-inline'; sandbox");
        header('Cache-Control: private, max-age=3600');
        echo $f['data'];
    }
}
