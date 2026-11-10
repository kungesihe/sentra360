<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\DB;
use App\Core\HttpException;
use App\Core\TenantContext;

/** Shared helpers for the operational modules. */
final class Ops
{
    /** Human-readable labels for every workflow status used in the app. */
    public const LABELS = [
        'draft' => 'Draft', 'submitted' => 'Submitted', 'received' => 'Received', 'under_review' => 'Under review',
        'assessment_required' => 'Assessment required', 'quotation_prepared' => 'Quotation prepared',
        'quotation_sent' => 'Quotation sent', 'awaiting_customer_acceptance' => 'Awaiting your acceptance',
        'awaiting_payment' => 'Awaiting payment', 'approved' => 'Approved', 'active' => 'Active',
        'rejected' => 'Rejected', 'cancelled' => 'Cancelled', 'assigned' => 'Assigned',
        'in_progress' => 'Response in progress', 'resolved' => 'Resolved', 'closed' => 'Closed',
        'confirmed' => 'Booking confirmed', 'completed' => 'Completed', 'sent' => 'Sent', 'accepted' => 'Accepted',
        'delivered' => 'Seen by control room', 'acknowledged' => 'Acknowledged', 'dispatched' => 'Officer dispatched',
        'pending' => 'Pending', 'paid' => 'Paid', 'failed' => 'Failed', 'refunded' => 'Refunded',
        'en_route' => 'En route', 'on_scene' => 'On scene', 'declined' => 'Declined', 'none' => 'Not protected',
    ];

    public static function label(?string $status): string
    {
        return self::LABELS[$status ?? ''] ?? ucfirst(str_replace('_', ' ', (string) $status));
    }

    public static function badge(?string $status): string
    {
        $map = [
            'submitted' => 'primary', 'received' => 'info', 'under_review' => 'info', 'assessment_required' => 'warning',
            'quotation_prepared' => 'secondary', 'quotation_sent' => 'warning', 'awaiting_customer_acceptance' => 'warning',
            'awaiting_payment' => 'warning', 'approved' => 'success', 'active' => 'success', 'confirmed' => 'success',
            'rejected' => 'danger', 'cancelled' => 'secondary', 'assigned' => 'info', 'in_progress' => 'primary',
            'resolved' => 'success', 'closed' => 'secondary', 'completed' => 'success', 'draft' => 'secondary',
            'sent' => 'warning', 'accepted' => 'success', 'delivered' => 'info', 'acknowledged' => 'primary',
            'dispatched' => 'primary', 'pending' => 'warning', 'paid' => 'success', 'failed' => 'danger',
            'en_route' => 'primary', 'on_scene' => 'primary', 'none' => 'secondary',
            'critical' => 'danger', 'high' => 'warning', 'normal' => 'secondary', 'low' => 'light',
        ];
        $cls = $map[$status ?? ''] ?? 'secondary';
        return '<span class="badge text-bg-' . $cls . '">' . e(self::label($status)) . '</span>';
    }

    /** Unique reference like INC-261110-7K3Q. */
    public static function reference(string $prefix, string $table): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        for ($i = 0; $i < 10; $i++) {
            $code = '';
            for ($j = 0; $j < 4; $j++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $ref = $prefix . '-' . date('ymd') . '-' . $code;
            if (!DB::value("SELECT 1 FROM `$table` WHERE reference = ?", [$ref])) {
                return $ref;
            }
        }
        throw new \RuntimeException('Could not generate a unique reference.');
    }

    /** Add an entry to a timeline. */
    public static function event(string $type, int $id, ?string $from, ?string $to, ?string $note = null, bool $internal = false): void
    {
        DB::insert('workflow_events', [
            'tenant_id' => TenantContext::id(), 'subject_type' => $type, 'subject_id' => $id,
            'from_status' => $from, 'to_status' => $to, 'note' => $note, 'is_internal' => $internal ? 1 : 0,
            'actor_id' => Auth::user()['id'] ?? null,
        ]);
    }

    public static function timeline(string $type, int $id, bool $includeInternal): array
    {
        return DB::all(
            'SELECT w.*, p.first_name, p.surname, r.code AS actor_role FROM workflow_events w
               LEFT JOIN users u ON u.id = w.actor_id LEFT JOIN user_profiles p ON p.user_id = u.id LEFT JOIN roles r ON r.id = u.role_id
              WHERE w.tenant_id = ? AND w.subject_type = ? AND w.subject_id = ?' . ($includeInternal ? '' : ' AND w.is_internal = 0') . '
              ORDER BY w.id',
            [TenantContext::id(), $type, $id]
        );
    }

    /** In-app notification (always delivered: it is shown inside the app). */
    public static function notify(int $userId, string $subject, string $body, ?string $link = null): void
    {
        DB::insert('notifications', [
            'tenant_id' => TenantContext::id(), 'user_id' => $userId, 'channel' => 'in_app',
            'recipient' => 'user:' . $userId, 'subject' => $subject, 'body' => $body, 'link' => $link,
            'status' => 'sent', 'sent_at' => now(),
        ]);
    }

    /** Notify every active staff member of a portal type. */
    public static function notifyStaff(string $roleCode, string $subject, string $body, ?string $link = null): void
    {
        $ids = DB::all(
            'SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE u.tenant_id = ? AND r.code = ? AND u.status = "active"',
            [TenantContext::id(), $roleCode]
        );
        foreach ($ids as $row) {
            self::notify((int) $row['id'], $subject, $body, $link);
        }
    }

    public static function unreadCount(int $userId): int
    {
        return (int) DB::value('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND channel = "in_app" AND read_at IS NULL', [$userId]);
    }

    // ------------------------------------------------------------------ files

    public const FILE_TYPES = [
        'image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp',
        'application/pdf' => 'pdf', 'video/mp4' => 'mp4', 'video/quicktime' => 'mov', 'video/webm' => 'webm',
    ];
    public const MAX_FILE_BYTES = 5 * 1024 * 1024;

    /**
     * Store uploaded files (input name="files[]") against a record.
     * @return string[] error messages for files that were refused
     */
    public static function storeUploads(string $ownerType, int $ownerId, string $field = 'files', int $max = 5, ?array $allowed = null): array
    {
        $allowed ??= array_keys(self::FILE_TYPES);
        $f = $_FILES[$field] ?? null;
        if (!$f || !is_array($f['name'])) {
            return [];
        }
        $errors = [];
        $stored = 0;
        foreach ($f['name'] as $i => $name) {
            if (($f['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $label = mb_substr(basename((string) $name), 0, 120);
            if ($stored >= $max) {
                $errors[] = "$label: only $max files can be attached.";
                continue;
            }
            if ($f['error'][$i] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'][$i])) {
                $errors[] = "$label: upload failed (files must be under 5 MB).";
                continue;
            }
            if ($f['size'][$i] > self::MAX_FILE_BYTES) {
                $errors[] = "$label: larger than 5 MB.";
                continue;
            }
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name'][$i]);
            if (!in_array($mime, $allowed, true)) {
                $errors[] = "$label: file type not allowed (photos, PDF or short MP4/WEBM videos only).";
                continue;
            }
            if (str_starts_with($mime, 'image/') && @getimagesize($f['tmp_name'][$i]) === false) {
                $errors[] = "$label: not a valid image.";
                continue;
            }
            DB::insert('files', [
                'tenant_id' => TenantContext::id(), 'owner_type' => $ownerType, 'owner_id' => $ownerId,
                'original_name' => $label, 'mime' => $mime, 'size_bytes' => (int) $f['size'][$i],
                'data' => (string) file_get_contents($f['tmp_name'][$i]), 'uploaded_by' => Auth::user()['id'] ?? null,
            ]);
            $stored++;
        }
        return $errors;
    }

    public static function filesFor(string $ownerType, int $ownerId): array
    {
        return DB::all(
            'SELECT id, original_name, mime, size_bytes, created_at FROM files WHERE tenant_id = ? AND owner_type = ? AND owner_id = ? ORDER BY id',
            [TenantContext::id(), $ownerType, $ownerId]
        );
    }

    /** Parse money typed by a person ("1 250,50" / "1250.5") into cents. */
    public static function cents(string $raw): ?int
    {
        $s = str_replace([' ', 'R', 'r'], '', trim($raw));
        $s = str_replace(',', '.', $s);
        if ($s === '' || !preg_match('/^\d+(\.\d{1,2})?$/', $s)) {
            return null;
        }
        return (int) round((float) $s * 100);
    }

    public static function requireOwnership(?array $row, int $userId, string $ownerField): array
    {
        if (!$row || (int) $row[$ownerField] !== $userId) {
            throw new HttpException(404);
        }
        return $row;
    }
}
