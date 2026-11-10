<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\DB;
use App\Core\HttpException;
use App\Core\TenantContext;

/**
 * The commercial workflow:
 * application / service request → review → quotation → acceptance → payment → active client.
 */
final class Workflow
{
    /** kind => [applications table, asset table, asset fk, reference prefix, label] */
    public const KINDS = [
        'property' => ['property_applications', 'registered_properties', 'property_id', 'PRA', 'Residential protection'],
        'vehicle'  => ['vehicle_applications', 'registered_vehicles', 'vehicle_id', 'VHA', 'Vehicle protection'],
    ];

    public const APP_STATUSES = ['draft', 'submitted', 'under_review', 'assessment_required', 'quotation_prepared', 'quotation_sent',
        'awaiting_customer_acceptance', 'awaiting_payment', 'approved', 'active', 'rejected', 'cancelled'];

    public static function kind(string $kind): array
    {
        return self::KINDS[$kind] ?? throw new HttpException(404);
    }

    public static function application(string $kind, int $id): ?array
    {
        [$table, $assetTable, $fk] = self::kind($kind);
        return DB::one(
            "SELECT a.*, p.first_name, p.surname, u.email, u.mobile FROM `$table` a
               JOIN users u ON u.id = a.applicant_id JOIN user_profiles p ON p.user_id = u.id
              WHERE a.id = ? AND a.tenant_id = ?",
            [$id, TenantContext::id()]
        );
    }

    public static function asset(string $kind, int $id): ?array
    {
        [, $assetTable] = self::kind($kind);
        return DB::one("SELECT * FROM `$assetTable` WHERE id = ? AND tenant_id = ?", [$id, TenantContext::id()]);
    }

    public static function setApplicationStatus(string $kind, array $app, string $status, ?string $note = null, bool $internal = false): void
    {
        if (!in_array($status, self::APP_STATUSES, true)) {
            throw new HttpException(400);
        }
        [$table, , , , $label] = self::kind($kind);
        $changes = ['status' => $status];
        if ($status === 'submitted' && !$app['submitted_at']) {
            $changes['submitted_at'] = now();
        }
        DB::update($table, $changes, ['id' => $app['id'], 'tenant_id' => TenantContext::id()]);
        Ops::event($kind . '_application', (int) $app['id'], $app['status'], $status, $note, $internal);
        Audit::log("application.$status", $kind . '_application', $app['id']);
        if (!$internal) {
            Ops::notify((int) $app['applicant_id'], "$label application {$app['reference']}: " . Ops::label($status),
                $note ?: 'Your application status changed to: ' . Ops::label($status) . '.', "/account/applications/$kind/{$app['id']}");
        }
    }

    /** Admin activates protection: application active, asset protected, customer becomes a client. */
    public static function activate(string $kind, array $app): void
    {
        [$table, $assetTable, $fk] = self::kind($kind);
        DB::transaction(function () use ($kind, $app, $assetTable, $fk) {
            DB::update($assetTable, ['protection_status' => 'active', 'activated_at' => now()], ['id' => $app[$fk], 'tenant_id' => TenantContext::id()]);
            DB::update('users', ['client_status' => 'client'], ['id' => $app['applicant_id'], 'tenant_id' => TenantContext::id()]);
            self::setApplicationStatus($kind, $app, 'active', 'Your protection is now active. The panic button is available in your account.');
        });
    }

    // ------------------------------------------------------------ quotations

    /** subject_type => [table, prefix for customer link, status after send, status after accept] */
    public static function subjectRow(string $type, int $id): ?array
    {
        return match ($type) {
            'property' => self::application('property', $id),
            'vehicle'  => self::application('vehicle', $id),
            'service'  => DB::one('SELECT * FROM service_requests WHERE id = ? AND tenant_id = ?', [$id, TenantContext::id()]),
            default    => null,
        };
    }

    public static function subjectLink(string $type, int $id, bool $admin): string
    {
        if ($type === 'service') {
            return $admin ? "/admin/requests/$id" : "/account/requests/$id";
        }
        return $admin ? "/admin/applications/$type/$id" : "/account/applications/$type/$id";
    }

    private static function setSubjectStatus(string $type, array $subject, string $status, ?string $note = null): void
    {
        if ($type === 'service') {
            DB::update('service_requests', ['status' => $status], ['id' => $subject['id'], 'tenant_id' => TenantContext::id()]);
            Ops::event('service_request', (int) $subject['id'], $subject['status'], $status, $note);
            Ops::notify((int) $subject['customer_id'], "Service request {$subject['reference']}: " . Ops::label($status),
                $note ?: 'Status changed to: ' . Ops::label($status) . '.', "/account/requests/{$subject['id']}");
            return;
        }
        self::setApplicationStatus($type, $subject, $status, $note);
    }

    /**
     * Create (and optionally send) a quotation.
     * @param array<int,array{description:string,quantity:float,unit_cents:int}> $items
     */
    public static function createQuotation(string $type, array $subject, array $items, string $billing, ?string $validUntil, ?string $notes, bool $vat, bool $send, int $createdBy): int
    {
        $customerId = (int) ($subject['applicant_id'] ?? $subject['customer_id']);
        $subtotal = 0;
        foreach ($items as &$it) {
            $it['line'] = (int) round($it['quantity'] * $it['unit_cents']);
            $subtotal += $it['line'];
        }
        unset($it);
        $vatCents = $vat ? (int) round($subtotal * (float) config('vat_rate')) : 0;
        return DB::transaction(function () use ($type, $subject, $items, $billing, $validUntil, $notes, $vat, $send, $createdBy, $customerId, $subtotal, $vatCents) {
            $qid = DB::insert('quotations', [
                'tenant_id' => TenantContext::id(), 'reference' => Ops::reference('QUO', 'quotations'),
                'customer_id' => $customerId, 'subject_type' => $type, 'subject_id' => $subject['id'],
                'status' => $send ? 'sent' : 'draft', 'billing' => $billing, 'valid_until' => $validUntil, 'notes' => $notes,
                'include_vat' => $vat ? 1 : 0, 'subtotal_cents' => $subtotal, 'vat_cents' => $vatCents,
                'total_cents' => $subtotal + $vatCents, 'created_by' => $createdBy, 'sent_at' => $send ? now() : null,
            ]);
            foreach ($items as $i => $it) {
                DB::insert('quotation_items', [
                    'tenant_id' => TenantContext::id(), 'quotation_id' => $qid, 'description' => $it['description'],
                    'quantity' => $it['quantity'], 'unit_price_cents' => $it['unit_cents'], 'line_total_cents' => $it['line'], 'sort_order' => $i,
                ]);
            }
            Audit::log('quotation.created', 'quotation', $qid, ['sent' => $send]);
            if ($send) {
                self::afterSend($type, $subject, $qid);
            } elseif ($type !== 'service') {
                self::setApplicationStatus($type, $subject, 'quotation_prepared', 'Quotation prepared (not yet sent).', true);
            }
            return $qid;
        });
    }

    public static function quotation(int $id): ?array
    {
        return DB::one(
            'SELECT q.*, p.first_name, p.surname, u.email, u.mobile FROM quotations q
               JOIN users u ON u.id = q.customer_id JOIN user_profiles p ON p.user_id = u.id
              WHERE q.id = ? AND q.tenant_id = ?',
            [$id, TenantContext::id()]
        );
    }

    public static function sendQuotation(array $q): void
    {
        if ($q['status'] !== 'draft') {
            throw new \RuntimeException('Only draft quotations can be sent.');
        }
        DB::update('quotations', ['status' => 'sent', 'sent_at' => now()], ['id' => $q['id'], 'tenant_id' => TenantContext::id()]);
        Audit::log('quotation.sent', 'quotation', $q['id']);
        self::afterSend($q['subject_type'], self::subjectRow($q['subject_type'], (int) $q['subject_id']), (int) $q['id']);
    }

    private static function afterSend(string $type, array $subject, int $qid): void
    {
        $q = DB::one('SELECT reference, total_cents FROM quotations WHERE id = ?', [$qid]);
        self::setSubjectStatus($type, $subject, 'awaiting_customer_acceptance',
            "Quotation {$q['reference']} for " . money_exact((int) $q['total_cents']) . ' is ready for you to review.');
        Ops::event('quotation', $qid, 'draft', 'sent', null);
    }

    /** Customer accepts or rejects a sent quotation. */
    public static function respond(array $q, bool $accept, ?string $reason = null): void
    {
        if ($q['status'] !== 'sent') {
            throw new \RuntimeException('This quotation can no longer be changed.');
        }
        $status = $accept ? 'accepted' : 'rejected';
        DB::update('quotations', ['status' => $status, 'responded_at' => now()], ['id' => $q['id'], 'tenant_id' => TenantContext::id()]);
        Ops::event('quotation', (int) $q['id'], 'sent', $status, $reason);
        Audit::log("quotation.$status", 'quotation', $q['id']);
        $subject = self::subjectRow($q['subject_type'], (int) $q['subject_id']);
        if ($accept) {
            $next = (int) $q['total_cents'] > 0 ? 'awaiting_payment' : ($q['subject_type'] === 'service' ? 'confirmed' : 'approved');
            self::setSubjectStatus($q['subject_type'], $subject, $next, "Quotation {$q['reference']} accepted.");
        } else {
            self::setSubjectStatus($q['subject_type'], $subject, 'under_review', "Quotation {$q['reference']} was declined" . ($reason ? ": $reason" : '.'));
        }
        Ops::notifyStaff('admin', "Quotation {$q['reference']} $status", "{$q['first_name']} {$q['surname']} $status quotation {$q['reference']}.", self::subjectLink($q['subject_type'], (int) $q['subject_id'], true));
    }

    public static function paidCents(int $quotationId): int
    {
        return (int) DB::value('SELECT COALESCE(SUM(amount_cents),0) FROM payments WHERE quotation_id = ? AND status = "paid"', [$quotationId]);
    }

    /** Record a payment (admin approves a proof of payment, or records cash or EFT received). */
    public static function recordPayment(int $customerId, ?array $q, int $amountCents, string $method, ?string $providerRef, ?string $notes, ?int $recordedBy, ?int $existingPaymentId = null): int
    {
        return DB::transaction(function () use ($customerId, $q, $amountCents, $method, $providerRef, $notes, $recordedBy, $existingPaymentId) {
            if ($existingPaymentId) {
                DB::update('payments', ['status' => 'paid', 'paid_at' => now(), 'provider_reference' => $providerRef], ['id' => $existingPaymentId]);
                $pid = $existingPaymentId;
            } else {
                $pid = DB::insert('payments', [
                    'tenant_id' => TenantContext::id(), 'reference' => Ops::reference('PAY', 'payments'), 'customer_id' => $customerId,
                    'quotation_id' => $q['id'] ?? null, 'amount_cents' => $amountCents, 'method' => $method, 'status' => 'paid',
                    'provider_reference' => $providerRef, 'notes' => $notes, 'recorded_by' => $recordedBy, 'paid_at' => now(),
                ]);
            }
            Audit::log('payment.recorded', 'payment', $pid, ['amount' => $amountCents, 'method' => $method]);
            Ops::notify($customerId, 'Payment received', 'We received your payment of ' . money_exact($amountCents) . '. Thank you.', '/account/payments');
            if ($q && $q['status'] === 'accepted' && self::paidCents((int) $q['id']) >= (int) $q['total_cents']) {
                $subject = self::subjectRow($q['subject_type'], (int) $q['subject_id']);
                if ($subject && $subject['status'] === 'awaiting_payment') {
                    self::setSubjectStatus($q['subject_type'], $subject, $q['subject_type'] === 'service' ? 'confirmed' : 'approved',
                        $q['subject_type'] === 'service' ? 'Payment received: your booking is confirmed.' : 'Payment received. We will activate your protection shortly.');
                }
            }
            return $pid;
        });
    }
}
