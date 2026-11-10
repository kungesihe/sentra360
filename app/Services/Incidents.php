<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\DB;
use App\Core\HttpException;
use App\Core\Notifier;
use App\Core\TenantContext;

/**
 * Incidents, panic alerts and response assignments.
 *
 * Reporting ≠ acceptance ≠ dispatch: a report starts as "submitted"; staff
 * opening it marks "received"; assigning an officer is the dispatch.
 */
final class Incidents
{
    public const STATUSES = ['submitted', 'received', 'under_review', 'assigned', 'in_progress', 'resolved', 'closed'];

    public static function find(int $id): ?array
    {
        return DB::one(
            'SELECT i.*, c.name AS category_name, c.group_code, p.first_name, p.surname, u.mobile AS reporter_mobile, u.email AS reporter_email
               FROM incidents i LEFT JOIN incident_categories c ON c.id = i.category_id
               LEFT JOIN users u ON u.id = i.reporter_id LEFT JOIN user_profiles p ON p.user_id = u.id
              WHERE i.id = ? AND i.tenant_id = ?',
            [$id, TenantContext::id()]
        );
    }

    public static function create(array $d, ?int $reporterId, string $source = 'community'): array
    {
        $cat = isset($d['category_id']) ? DB::one('SELECT * FROM incident_categories WHERE id = ?', [$d['category_id']]) : null;
        $priority = $d['priority'] ?? ($cat['priority'] ?? 'normal');
        if (!empty($d['assistance_required']) && $priority !== 'critical') {
            $priority = 'high';
        }
        $id = DB::insert('incidents', [
            'tenant_id' => TenantContext::id(), 'reference' => Ops::reference('INC', 'incidents'), 'source' => $source,
            'reporter_id' => $reporterId, 'category_id' => $cat['id'] ?? null, 'title' => $d['title'] ?? ($cat['name'] ?? 'Incident'),
            'priority' => $priority, 'status' => 'submitted', 'occurred_at' => $d['occurred_at'] ?? now(),
            'location_text' => $d['location_text'] ?? null, 'latitude' => $d['latitude'] ?? null, 'longitude' => $d['longitude'] ?? null,
            'description' => $d['description'] ?? null, 'witness_details' => $d['witness_details'] ?? null,
            'injuries' => $d['injuries'] ?? null, 'assistance_required' => empty($d['assistance_required']) ? 0 : 1,
            'property_id' => $d['property_id'] ?? null, 'vehicle_id' => $d['vehicle_id'] ?? null,
        ]);
        Ops::event('incident', $id, null, 'submitted', $source === 'panic' ? 'Panic alert raised by client.' : 'Report submitted.');
        Audit::log('incident.created', 'incident', $id, ['source' => $source, 'priority' => $priority]);
        $inc = self::find($id);
        $msg = "{$inc['reference']} · {$inc['title']}" . ($inc['location_text'] ? " · {$inc['location_text']}" : '');
        Ops::notifyStaff('security_staff', ($priority === 'critical' ? 'URGENT: ' : '') . 'New incident', $msg, "/security/incidents/$id");
        Ops::notifyStaff('admin', 'New incident report', $msg, "/admin/incidents/$id");
        return $inc;
    }

    public static function setStatus(array $inc, string $status, ?string $note = null, bool $internal = false): void
    {
        if (!in_array($status, self::STATUSES, true)) {
            throw new HttpException(400);
        }
        $changes = ['status' => $status];
        if (in_array($status, ['resolved', 'closed'], true) && !$inc['resolved_at']) {
            $changes['resolved_at'] = now();
        }
        if ($status === 'received' && !$inc['received_at']) {
            $changes['received_at'] = now();
        }
        DB::update('incidents', $changes, ['id' => $inc['id'], 'tenant_id' => TenantContext::id()]);
        Ops::event('incident', (int) $inc['id'], $inc['status'], $status, $note, $internal);
        Audit::log('incident.status', 'incident', $inc['id'], ['to' => $status]);
        if (!$internal && $inc['reporter_id']) {
            Ops::notify((int) $inc['reporter_id'], "Incident {$inc['reference']}: " . Ops::label($status), $note ?: 'Status updated.', "/account/incidents/{$inc['id']}");
        }
        if (in_array($status, ['resolved', 'closed'], true)) {
            DB::run('UPDATE panic_alerts SET status = "resolved", resolved_at = COALESCE(resolved_at, NOW()) WHERE incident_id = ? AND status NOT IN ("resolved","cancelled")', [$inc['id']]);
        }
    }

    /** First time staff open a submitted report, it becomes "received". */
    public static function markReceived(array $inc): array
    {
        if ($inc['status'] === 'submitted') {
            self::setStatus($inc, 'received', 'Your report has been received by ' . TenantContext::current()['display_name'] . '. This does not yet mean a response has been dispatched.');
            $inc = self::find((int) $inc['id']);
        }
        return $inc;
    }

    public static function assign(array $inc, int $officerId): void
    {
        $officer = DB::one('SELECT u.id, p.first_name, p.surname FROM users u JOIN roles r ON r.id = u.role_id JOIN user_profiles p ON p.user_id = u.id
                             WHERE u.id = ? AND u.tenant_id = ? AND r.code = "security_staff" AND u.status = "active"', [$officerId, TenantContext::id()]);
        if (!$officer) {
            throw new \RuntimeException('Choose an active security officer.');
        }
        DB::insert('incident_assignments', [
            'tenant_id' => TenantContext::id(), 'incident_id' => $inc['id'], 'officer_id' => $officerId, 'assigned_by' => Auth::user()['id'] ?? null,
        ]);
        self::setStatus($inc, 'assigned', "Response officer {$officer['first_name']} {$officer['surname']} has been assigned.");
        DB::run('UPDATE panic_alerts SET status = "dispatched", dispatched_at = NOW() WHERE incident_id = ? AND status IN ("submitted","delivered","acknowledged")', [$inc['id']]);
        Ops::notify($officerId, "Assigned: {$inc['reference']}", "{$inc['title']}" . ($inc['location_text'] ? " at {$inc['location_text']}" : ''), "/security/incidents/{$inc['id']}");
    }

    public static function assignments(int $incidentId): array
    {
        return DB::all(
            'SELECT a.*, p.first_name, p.surname FROM incident_assignments a JOIN user_profiles p ON p.user_id = a.officer_id
              WHERE a.incident_id = ? AND a.tenant_id = ? ORDER BY a.id',
            [$incidentId, TenantContext::id()]
        );
    }

    /** Officer updates their own assignment. */
    public static function officerUpdate(array $assignment, array $inc, string $status, ?string $note): void
    {
        $allowed = ['accepted', 'en_route', 'on_scene', 'completed', 'declined'];
        if (!in_array($status, $allowed, true)) {
            throw new HttpException(400);
        }
        $changes = ['status' => $status];
        if ($status === 'accepted') {
            $changes['accepted_at'] = now();
        }
        if ($status === 'on_scene') {
            $changes['arrived_at'] = now();
        }
        if ($status === 'completed') {
            $changes['completed_at'] = now();
        }
        DB::update('incident_assignments', $changes, ['id' => $assignment['id'], 'tenant_id' => TenantContext::id()]);
        $msg = match ($status) {
            'accepted' => 'The response officer accepted the assignment.',
            'en_route' => 'A response officer is on the way.',
            'on_scene' => 'The response officer has arrived.',
            'completed' => 'The response officer completed their response.',
            'declined' => 'The assigned officer declined; the control room will reassign.',
        };
        Ops::event('incident', (int) $inc['id'], $inc['status'], $inc['status'], $msg . ($note ? " $note" : ''), false);
        if (in_array($status, ['en_route', 'on_scene'], true) && in_array($inc['status'], ['assigned', 'received', 'under_review', 'submitted'], true)) {
            self::setStatus($inc, 'in_progress', $msg);
        } elseif ($inc['reporter_id']) {
            Ops::notify((int) $inc['reporter_id'], "Incident {$inc['reference']}", $msg, "/account/incidents/{$inc['id']}");
        }
    }

    // ------------------------------------------------------------------ panic

    /**
     * What is missing from a homeowner's profile before the panic button unlocks.
     * A complete profile (name, mobile, email + home address + emergency contact)
     * lets the control room find the person and reach someone close to them.
     * @return array<int,array{0:string,1:string,2:string}> [key, what is missing, where to add it]
     */
    public static function profileGaps(array $user): array
    {
        $tid = TenantContext::id();
        $gaps = [];
        if (trim((string) $user['first_name']) === '' || trim((string) $user['surname']) === '') {
            $gaps[] = ['name', 'Your full name', '/account/profile'];
        }
        if (!DB::value('SELECT 1 FROM registered_properties WHERE tenant_id = ? AND owner_id = ?', [$tid, $user['id']])) {
            $gaps[] = ['address', 'Your home address (register your property)', '/account/assets/property/new'];
        }
        if (!DB::value('SELECT 1 FROM emergency_contacts WHERE tenant_id = ? AND user_id = ?', [$tid, $user['id']])) {
            $gaps[] = ['contact', 'At least one emergency contact', '/account/contacts'];
        }
        return $gaps;
    }

    /** The panic button is for everyone with a complete profile: clients and community members. */
    public static function panicEligible(array $user): bool
    {
        return self::profileGaps($user) === [];
    }

    /** Does this person have an active protection service (paying client)? */
    public static function isClient(array $user): bool
    {
        if ($user['client_status'] === 'client') {
            return true;
        }
        $tid = TenantContext::id();
        return (bool) DB::value('SELECT 1 FROM registered_properties WHERE tenant_id = ? AND owner_id = ? AND protection_status = "active"', [$tid, $user['id']])
            || (bool) DB::value('SELECT 1 FROM registered_vehicles WHERE tenant_id = ? AND owner_id = ? AND protection_status = "active"', [$tid, $user['id']]);
    }

    public static function triggerPanic(array $user, array $d): array
    {
        $tid = TenantContext::id();
        $lat = $d['latitude'];
        $lng = $d['longitude'];
        $where = null;
        $propertyId = null;
        $vehicleId = null;
        if (!empty($d['property_id']) && ($p = DB::one('SELECT * FROM registered_properties WHERE id = ? AND tenant_id = ? AND owner_id = ?', [$d['property_id'], $tid, $user['id']]))) {
            $propertyId = (int) $p['id'];
            $where = "{$p['name']}, {$p['address']}, {$p['suburb']}";
            $lat ??= $p['latitude'];
            $lng ??= $p['longitude'];
        }
        if (!empty($d['vehicle_id']) && ($v = DB::one('SELECT * FROM registered_vehicles WHERE id = ? AND tenant_id = ? AND owner_id = ?', [$d['vehicle_id'], $tid, $user['id']]))) {
            $vehicleId = (int) $v['id'];
            $where = trim(($where ? "$where · " : '') . "Vehicle {$v['colour']} {$v['make']} {$v['model']} ({$v['registration']})");
        }
        return DB::transaction(function () use ($user, $d, $tid, $lat, $lng, $where, $propertyId, $vehicleId) {
            $inc = self::create([
                'title' => 'PANIC ALERT', 'priority' => 'critical', 'occurred_at' => now(),
                'location_text' => $where ?? ($d['latitude'] ? 'GPS location' : 'Location not available'),
                'latitude' => $lat, 'longitude' => $lng,
                'description' => 'Panic button activated by ' . $user['first_name'] . ' ' . $user['surname'] . ($d['extra_info'] ? ". Client note: {$d['extra_info']}" : '.'),
                'assistance_required' => 1, 'property_id' => $propertyId, 'vehicle_id' => $vehicleId,
            ], (int) $user['id'], 'panic');
            $pid = DB::insert('panic_alerts', [
                'tenant_id' => $tid, 'reference' => Ops::reference('SOS', 'panic_alerts'), 'customer_id' => $user['id'],
                'incident_id' => $inc['id'], 'property_id' => $propertyId, 'vehicle_id' => $vehicleId,
                'latitude' => $d['latitude'], 'longitude' => $d['longitude'], 'accuracy_m' => $d['accuracy_m'],
                'location_status' => $d['location_status'], 'silent' => $d['silent'] ? 1 : 0, 'extra_info' => $d['extra_info'],
            ]);
            if ($d['latitude'] !== null) {
                DB::insert('panic_locations', ['tenant_id' => $tid, 'panic_alert_id' => $pid, 'latitude' => $d['latitude'], 'longitude' => $d['longitude'], 'accuracy_m' => $d['accuracy_m']]);
            }
            Audit::log('panic.triggered', 'panic_alert', $pid);
            // Optional emergency-contact SMS. Recorded honestly: not_configured until an SMS provider exists.
            $contactStatus = [];
            if ($d['notify_contacts']) {
                foreach (DB::all('SELECT * FROM emergency_contacts WHERE tenant_id = ? AND user_id = ? AND notify_on_panic = 1', [$tid, $user['id']]) as $c) {
                    $contactStatus[] = Notifier::sms($c['mobile'], "EMERGENCY: {$user['first_name']} {$user['surname']} pressed the panic button with " . TenantContext::current()['display_name'] . '. The control room has been alerted.', $tid, (int) $user['id']);
                }
            }
            $_SESSION['panic_contacts'] = $contactStatus;
            return DB::one('SELECT * FROM panic_alerts WHERE id = ?', [$pid]);
        });
    }

    public static function panicForCustomer(string $ref, int $userId): ?array
    {
        return DB::one('SELECT * FROM panic_alerts WHERE reference = ? AND tenant_id = ? AND customer_id = ?', [$ref, TenantContext::id(), $userId]);
    }

    /** Control room has loaded the alert on screen → "delivered". */
    public static function markDelivered(array $ids): void
    {
        if (!$ids) {
            return;
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        DB::run("UPDATE panic_alerts SET status = 'delivered', delivered_at = NOW() WHERE tenant_id = ? AND status = 'submitted' AND id IN ($in)", array_merge([TenantContext::id()], $ids));
    }

    public static function acknowledgePanic(array $alert): void
    {
        if (in_array($alert['status'], ['submitted', 'delivered'], true)) {
            DB::update('panic_alerts', ['status' => 'acknowledged', 'acknowledged_at' => now(), 'acknowledged_by' => Auth::user()['id']],
                ['id' => $alert['id'], 'tenant_id' => TenantContext::id()]);
            if ($inc = self::find((int) $alert['incident_id'])) {
                if ($inc['status'] === 'submitted') {
                    self::setStatus($inc, 'received', 'The control room has acknowledged your panic alert.');
                } else {
                    Ops::event('incident', (int) $inc['id'], $inc['status'], $inc['status'], 'Panic alert acknowledged by the control room.');
                }
            }
            Audit::log('panic.acknowledged', 'panic_alert', $alert['id']);
        }
    }
}
