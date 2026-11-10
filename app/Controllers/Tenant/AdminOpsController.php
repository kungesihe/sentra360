<?php
declare(strict_types=1);

namespace App\Controllers\Tenant;

use App\Controllers\Controller;
use App\Core\Audit;
use App\Core\Auth;
use App\Core\DB;
use App\Core\HttpException;
use App\Core\TenantContext;
use App\Services\Ops;
use App\Services\Workflow;

/** Admin portal: applications, quotations, services, bookings, payments, safety alerts, reports. */
final class AdminOpsController extends Controller
{
    private function render(string $view, array $data): void
    {
        $this->view('admin/' . $view, $data + ['me' => Auth::user(), 'portal' => 'admin'], 'portal');
    }

    // ---------------------------------------------------------- applications

    public function applications(): void
    {
        $tid = TenantContext::id();
        $status = (string) ($_GET['status'] ?? 'open');
        $kindF = (string) ($_GET['kind'] ?? '');
        $where = match (true) {
            $status === 'open' => 'a.status NOT IN ("draft","active","rejected","cancelled")',
            in_array($status, Workflow::APP_STATUSES, true) => 'a.status = ' . DB::pdo()->quote($status),
            default => '1=1',
        };
        $rows = DB::all(
            "SELECT * FROM (
               SELECT 'property' AS kind, a.id, a.reference, a.status, a.created_at, a.updated_at, CONCAT(r.name, ' · ', r.suburb) AS label, p.first_name, p.surname
                 FROM property_applications a JOIN registered_properties r ON r.id = a.property_id JOIN user_profiles p ON p.user_id = a.applicant_id
                WHERE a.tenant_id = ? AND $where
               UNION ALL
               SELECT 'vehicle', a.id, a.reference, a.status, a.created_at, a.updated_at, CONCAT(v.make, ' ', v.model, ' · ', v.registration), p.first_name, p.surname
                 FROM vehicle_applications a JOIN registered_vehicles v ON v.id = a.vehicle_id JOIN user_profiles p ON p.user_id = a.applicant_id
                WHERE a.tenant_id = ? AND $where
             ) x " . (in_array($kindF, ['property', 'vehicle'], true) ? 'WHERE kind = ' . DB::pdo()->quote($kindF) : '') . ' ORDER BY updated_at DESC LIMIT 300',
            [$tid, $tid]
        );
        $this->render('applications', ['title' => 'Protection applications', 'rows' => $rows, 'status' => $status, 'kindF' => $kindF]);
    }

    public function application(string $kind, string $id): void
    {
        $app = Workflow::application($kind, (int) $id) ?? throw new HttpException(404);
        [, , $fk] = Workflow::kind($kind);
        Audit::log('application.viewed', $kind . '_application', $app['id']);
        $this->render('application', [
            'title' => Workflow::KINDS[$kind][4] . ' · ' . $app['reference'], 'kind' => $kind, 'app' => $app,
            'asset' => Workflow::asset($kind, (int) $app[$fk]),
            'files' => Ops::filesFor($kind, (int) $app[$fk]),
            'timeline' => Ops::timeline($kind . '_application', (int) $app['id'], true),
            'quotes' => DB::all('SELECT * FROM quotations WHERE tenant_id = ? AND subject_type = ? AND subject_id = ? ORDER BY id DESC', [TenantContext::id(), $kind, $app['id']]),
        ]);
    }

    public function applicationStatus(string $kind, string $id): void
    {
        $app = Workflow::application($kind, (int) $id) ?? throw new HttpException(404);
        $action = (string) ($_POST['action'] ?? '');
        $note = mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 2000) ?: null;
        match ($action) {
            'review'     => Workflow::setApplicationStatus($kind, $app, 'under_review', $note ?? 'We are reviewing your application.'),
            'assessment' => Workflow::setApplicationStatus($kind, $app, 'assessment_required', $note ?? 'We need to do a security assessment. We will contact you to arrange a time.'),
            'info'       => Workflow::setApplicationStatus($kind, $app, 'under_review', 'Additional information requested: ' . ($note ?? 'please contact us.')),
            'approve'    => Workflow::setApplicationStatus($kind, $app, 'approved', $note ?? 'Approved.'),
            'activate'   => Workflow::activate($kind, $app),
            'reject'     => Workflow::setApplicationStatus($kind, $app, 'rejected', $note ?? 'Unfortunately we cannot offer this service.'),
            'note'       => $note ? Ops::event($kind . '_application', (int) $app['id'], $app['status'], $app['status'], $note, true) : null,
            default      => throw new HttpException(400),
        };
        flash('success', 'Application updated.');
        redirect("/admin/applications/$kind/{$app['id']}");
    }

    // ------------------------------------------------------------ quotations

    public function quoteForm(string $type, string $id): void
    {
        $subject = Workflow::subjectRow($type, (int) $id) ?? throw new HttpException(404);
        $prefill = [];
        if ($type === 'service') {
            $svc = DB::one('SELECT * FROM services WHERE id = ?', [$subject['service_id']]);
            $prefill[] = ['description' => $svc['name'] . ' · ' . $subject['requested_date'] . ($subject['duration_text'] ? ' (' . $subject['duration_text'] . ')' : ''), 'qty' => 1, 'price' => $svc['price_cents'] ? number_format($svc['price_cents'] / 100, 2, '.', '') : ''];
        } else {
            foreach (json_decode((string) $subject['services_requested'], true) ?: [] as $s) {
                $prefill[] = ['description' => $s, 'qty' => 1, 'price' => ''];
            }
        }
        $customer = DB::one('SELECT p.first_name, p.surname FROM user_profiles p WHERE p.user_id = ?', [$subject['applicant_id'] ?? $subject['customer_id']]);
        $this->render('quote_form', ['title' => 'Prepare quotation · ' . $subject['reference'], 'type' => $type, 'subject' => $subject, 'prefill' => $prefill, 'customer' => $customer]);
    }

    public function quoteStore(string $type, string $id): void
    {
        $subject = Workflow::subjectRow($type, (int) $id) ?? throw new HttpException(404);
        $items = [];
        $errors = [];
        foreach ((array) ($_POST['desc'] ?? []) as $i => $desc) {
            $desc = mb_substr(trim((string) $desc), 0, 255);
            if ($desc === '') {
                continue;
            }
            $qty = (float) str_replace(',', '.', (string) ($_POST['qty'][$i] ?? '1'));
            $cents = Ops::cents((string) ($_POST['price'][$i] ?? ''));
            if ($qty <= 0 || $qty > 10000 || $cents === null) {
                $errors[] = "Line \"$desc\": enter a quantity and a price like 1250.00.";
                continue;
            }
            $items[] = ['description' => $desc, 'quantity' => $qty, 'unit_cents' => $cents];
        }
        if (!$items && !$errors) {
            $errors[] = 'Add at least one line item.';
        }
        $valid = (string) ($_POST['valid_until'] ?? '');
        if ($valid !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $valid)) {
            $errors[] = 'Valid-until must be a date.';
        }
        if ($errors) {
            $this->backWithErrors("/admin/quotations/new/$type/{$subject['id']}", $errors);
        }
        $send = ($_POST['send'] ?? '') === '1';
        $qid = Workflow::createQuotation($type, $subject, $items, ($_POST['billing'] ?? '') === 'monthly' ? 'monthly' : 'once_off',
            $valid ?: null, mb_substr(trim((string) ($_POST['notes'] ?? '')), 0, 3000) ?: null, !empty($_POST['vat']), $send, (int) Auth::user()['id']);
        flash('success', $send ? 'Quotation sent to the customer.' : 'Quotation saved as a draft.');
        redirect("/admin/quotations/$qid");
    }

    public function quotations(): void
    {
        $status = (string) ($_GET['status'] ?? '');
        $rows = DB::all(
            'SELECT q.*, p.first_name, p.surname FROM quotations q JOIN user_profiles p ON p.user_id = q.customer_id
              WHERE q.tenant_id = ?' . (in_array($status, ['draft', 'sent', 'accepted', 'rejected', 'cancelled'], true) ? ' AND q.status = ' . DB::pdo()->quote($status) : '') . ' ORDER BY q.id DESC LIMIT 300',
            [TenantContext::id()]
        );
        $this->render('quotations', ['title' => 'Quotations', 'rows' => $rows, 'status' => $status]);
    }

    public function quotation(string $id): void
    {
        $q = Workflow::quotation((int) $id) ?? throw new HttpException(404);
        $this->render('quotation', [
            'title' => 'Quotation ' . $q['reference'], 'q' => $q,
            'items' => DB::all('SELECT * FROM quotation_items WHERE quotation_id = ? ORDER BY sort_order', [$q['id']]),
            'payments' => DB::all('SELECT * FROM payments WHERE quotation_id = ? ORDER BY id', [$q['id']]),
            'paid' => Workflow::paidCents((int) $q['id']),
            'subjectLink' => Workflow::subjectLink($q['subject_type'], (int) $q['subject_id'], true),
        ]);
    }

    public function quotationAction(string $id): void
    {
        $q = Workflow::quotation((int) $id) ?? throw new HttpException(404);
        try {
            match ((string) ($_POST['action'] ?? '')) {
                'send' => Workflow::sendQuotation($q),
                'cancel' => in_array($q['status'], ['draft', 'sent'], true)
                    ? DB::update('quotations', ['status' => 'cancelled'], ['id' => $q['id'], 'tenant_id' => TenantContext::id()])
                    : throw new \RuntimeException('Only draft or sent quotations can be cancelled.'),
                default => throw new HttpException(400),
            };
            flash('success', 'Quotation updated.');
        } catch (\RuntimeException $e) {
            if ($e instanceof HttpException) {
                throw $e;
            }
            flash('danger', $e->getMessage());
        }
        redirect("/admin/quotations/{$q['id']}");
    }

    // -------------------------------------------------------------- payments

    public function payments(): void
    {
        $rows = DB::all(
            'SELECT pm.*, p.first_name, p.surname, q.reference AS quote_ref FROM payments pm JOIN user_profiles p ON p.user_id = pm.customer_id
               LEFT JOIN quotations q ON q.id = pm.quotation_id WHERE pm.tenant_id = ? ORDER BY pm.id DESC LIMIT 300',
            [TenantContext::id()]
        );
        $this->render('payments', ['title' => 'Payments', 'rows' => $rows, 'payfast' => \App\Services\PayFast::configured()]);
    }

    public function paymentStore(string $id): void
    {
        $q = Workflow::quotation((int) $id) ?? throw new HttpException(404);
        $cents = Ops::cents((string) ($_POST['amount'] ?? ''));
        $method = (string) ($_POST['method'] ?? '');
        if (!$cents || !in_array($method, ['eft', 'cash', 'card', 'debit_order', 'other'], true)) {
            $this->backWithErrors("/admin/quotations/{$q['id']}", ['amount' => 'Enter the amount received and how it was paid.']);
        }
        if ($q['status'] !== 'accepted') {
            $this->backWithErrors("/admin/quotations/{$q['id']}", ['amount' => 'Payments can only be recorded against an accepted quotation.']);
        }
        Workflow::recordPayment((int) $q['customer_id'], $q, $cents, $method, mb_substr(trim((string) ($_POST['provider_reference'] ?? '')), 0, 100) ?: null,
            mb_substr(trim((string) ($_POST['notes'] ?? '')), 0, 255) ?: null, (int) Auth::user()['id']);
        flash('success', 'Payment recorded.');
        redirect("/admin/quotations/{$q['id']}");
    }

    // -------------------------------------------------------------- services

    public function services(): void
    {
        $rows = DB::all('SELECT s.*, (SELECT COUNT(*) FROM service_requests r WHERE r.service_id = s.id) AS requests FROM services s WHERE s.tenant_id = ? ORDER BY s.category, s.sort_order, s.name', [TenantContext::id()]);
        $this->render('services', ['title' => 'Services', 'rows' => $rows]);
    }

    public function serviceForm(string $id = 'new'): void
    {
        $s = $id === 'new' ? null : (DB::one('SELECT * FROM services WHERE id = ? AND tenant_id = ?', [(int) $id, TenantContext::id()]) ?? throw new HttpException(404));
        $this->render('service_form', ['title' => $s ? 'Edit ' . $s['name'] : 'Add a service', 's' => $s, 'photos' => $s ? Ops::filesFor('service', (int) $s['id']) : []]);
    }

    public function serviceSave(string $id = 'new'): void
    {
        $tid = TenantContext::id();
        $existing = $id === 'new' ? null : (DB::one('SELECT * FROM services WHERE id = ? AND tenant_id = ?', [(int) $id, $tid]) ?? throw new HttpException(404));
        $v = $this->validate([
            'name' => 'required,max:120', 'category' => 'required,max:60', 'description' => 'required,max:5000',
            'pricing' => 'required,in:fixed|from|quote', 'price_unit' => 'max:40', 'duration_text' => 'max:100',
            'service_area' => 'max:190', 'availability_text' => 'max:190', 'requirements' => 'max:3000',
        ]);
        $price = null;
        if (($v->clean['pricing'] ?? 'quote') !== 'quote') {
            $price = Ops::cents((string) ($_POST['price'] ?? ''));
            if ($price === null) {
                $v->addError('price', 'Enter a price like 1500.00, or choose "Quotation required".');
            }
        }
        $back = $existing ? "/admin/services/{$existing['id']}" : '/admin/services/new';
        if ($v->fails()) {
            $this->backWithErrors($back, $v->errors);
        }
        $data = $v->clean + ['price_cents' => $price, 'is_active' => empty($_POST['is_active']) ? 0 : 1];
        if ($existing) {
            DB::update('services', $data, ['id' => $existing['id'], 'tenant_id' => $tid]);
            $sid = (int) $existing['id'];
        } else {
            $sid = DB::insert('services', $data + ['tenant_id' => $tid, 'sort_order' => 99]);
        }
        foreach (Ops::storeUploads('service', $sid, 'files', 4, ['image/png', 'image/jpeg', 'image/webp']) as $e) {
            flash('warning', 'Not attached: ' . $e);
        }
        if (!empty($_POST['remove_photos'])) {
            DB::run('DELETE FROM files WHERE tenant_id = ? AND owner_type = "service" AND owner_id = ? AND id IN (' . implode(',', array_map('intval', (array) $_POST['remove_photos'])) . ')', [$tid, $sid]);
        }
        Audit::log('service.saved', 'service', $sid);
        flash('success', 'Service saved.');
        redirect('/admin/services');
    }

    // ------------------------------------------------------ service requests

    public function requests(): void
    {
        $status = (string) ($_GET['status'] ?? 'open');
        $where = $status === 'open' ? ' AND r.status NOT IN ("completed","cancelled","rejected")' : (preg_match('/^[a-z_]+$/', $status) && $status !== 'all' ? ' AND r.status = ' . DB::pdo()->quote($status) : '');
        $rows = DB::all(
            "SELECT r.*, s.name AS service_name, p.first_name, p.surname FROM service_requests r JOIN services s ON s.id = r.service_id
               JOIN user_profiles p ON p.user_id = r.customer_id WHERE r.tenant_id = ? $where ORDER BY r.requested_date, r.id DESC LIMIT 300",
            [TenantContext::id()]
        );
        $this->render('requests', ['title' => 'Service requests & bookings', 'rows' => $rows, 'status' => $status]);
    }

    public function request(string $id): void
    {
        $r = DB::one('SELECT r.*, s.name AS service_name, s.pricing, s.price_cents, p.first_name, p.surname, u.mobile, u.email FROM service_requests r
                        JOIN services s ON s.id = r.service_id JOIN users u ON u.id = r.customer_id JOIN user_profiles p ON p.user_id = u.id
                       WHERE r.id = ? AND r.tenant_id = ?', [(int) $id, TenantContext::id()]) ?? throw new HttpException(404);
        $this->render('request', [
            'title' => 'Request ' . $r['reference'], 'r' => $r,
            'timeline' => Ops::timeline('service_request', (int) $r['id'], true),
            'quotes' => DB::all('SELECT * FROM quotations WHERE tenant_id = ? AND subject_type = "service" AND subject_id = ? ORDER BY id DESC', [TenantContext::id(), $r['id']]),
        ]);
    }

    public function requestStatus(string $id): void
    {
        $r = DB::one('SELECT * FROM service_requests WHERE id = ? AND tenant_id = ?', [(int) $id, TenantContext::id()]) ?? throw new HttpException(404);
        $map = ['review' => 'under_review', 'confirm' => 'confirmed', 'complete' => 'completed', 'reject' => 'rejected'];
        $to = $map[(string) ($_POST['action'] ?? '')] ?? throw new HttpException(400);
        $note = mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 2000) ?: null;
        DB::update('service_requests', ['status' => $to], ['id' => $r['id']]);
        Ops::event('service_request', (int) $r['id'], $r['status'], $to, $note);
        Ops::notify((int) $r['customer_id'], "Request {$r['reference']}: " . Ops::label($to), $note ?? 'Status updated.', "/account/requests/{$r['id']}");
        flash('success', 'Request updated.');
        redirect("/admin/requests/{$r['id']}");
    }

    // --------------------------------------------------------- safety alerts

    public function alerts(): void
    {
        $this->render('alerts', ['title' => 'Community safety alerts', 'rows' => DB::all('SELECT * FROM safety_alerts WHERE tenant_id = ? ORDER BY id DESC LIMIT 100', [TenantContext::id()])]);
    }

    public function alertStore(): void
    {
        $v = $this->validate(['title' => 'required,max:150', 'body' => 'required,max:3000', 'level' => 'required,in:info|warning|urgent', 'area' => 'max:150']);
        $days = max(1, min(60, (int) ($_POST['days'] ?? 7)));
        if ($v->fails()) {
            $this->backWithErrors('/admin/alerts', $v->errors);
        }
        DB::insert('safety_alerts', $v->clean + ['tenant_id' => TenantContext::id(), 'published_by' => Auth::user()['id'], 'expires_at' => date('Y-m-d H:i:s', strtotime("+$days days"))]);
        Audit::log('safety_alert.published', 'safety_alert', null, ['title' => $v->clean['title']]);
        flash('success', 'Alert published on the public site and homeowner dashboards.');
        redirect('/admin/alerts');
    }

    public function alertEnd(string $id): void
    {
        DB::run('UPDATE safety_alerts SET expires_at = NOW() WHERE id = ? AND tenant_id = ?', [(int) $id, TenantContext::id()]);
        redirect('/admin/alerts');
    }

    // ---------------------------------------------------------------- reports

    public function reports(): void
    {
        $tid = TenantContext::id();
        $days = in_array((int) ($_GET['days'] ?? 30), [7, 30, 90, 365], true) ? (int) $_GET['days'] : 30;
        $since = date('Y-m-d H:i:s', strtotime("-$days days"));
        $byCategory = DB::all('SELECT COALESCE(c.name, i.title) AS name, COUNT(*) n FROM incidents i LEFT JOIN incident_categories c ON c.id = i.category_id
                                WHERE i.tenant_id = ? AND i.created_at >= ? GROUP BY name ORDER BY n DESC LIMIT 12', [$tid, $since]);
        $byStatus = DB::all('SELECT status, COUNT(*) n FROM incidents WHERE tenant_id = ? AND created_at >= ? GROUP BY status', [$tid, $since]);
        $response = DB::one(
            'SELECT AVG(TIMESTAMPDIFF(SECOND, pa.created_at, pa.acknowledged_at)) AS ack_s,
                    AVG(TIMESTAMPDIFF(SECOND, pa.created_at, pa.dispatched_at)) AS dispatch_s, COUNT(*) AS n
               FROM panic_alerts pa WHERE pa.tenant_id = ? AND pa.created_at >= ?', [$tid, $since]);
        $arrival = DB::value('SELECT AVG(TIMESTAMPDIFF(SECOND, a.assigned_at, a.arrived_at)) FROM incident_assignments a WHERE a.tenant_id = ? AND a.arrived_at IS NOT NULL AND a.assigned_at >= ?', [$tid, $since]);
        $apps = DB::one(
            "SELECT COUNT(*) total, SUM(status = 'active') active, SUM(status IN ('rejected','cancelled')) lost FROM (
               SELECT status FROM property_applications WHERE tenant_id = ? AND created_at >= ?
               UNION ALL SELECT status FROM vehicle_applications WHERE tenant_id = ? AND created_at >= ?) x", [$tid, $since, $tid, $since]);
        $quotes = DB::one('SELECT COUNT(*) sent, SUM(status = "accepted") accepted FROM quotations WHERE tenant_id = ? AND sent_at >= ?', [$tid, $since]);
        $revenue = DB::all("SELECT DATE_FORMAT(paid_at, '%Y-%m') AS month, SUM(amount_cents) cents FROM payments WHERE tenant_id = ? AND status = 'paid' AND paid_at >= ? GROUP BY month ORDER BY month", [$tid, date('Y-m-01', strtotime('-11 months'))]);
        $homeownerRole = (int) DB::value('SELECT id FROM roles WHERE code = "homeowner"');
        $growth = DB::one('SELECT COUNT(*) total, SUM(created_at >= ?) recent, SUM(client_status = "client") clients FROM users WHERE tenant_id = ? AND role_id = ?', [$since, $tid, $homeownerRole]);
        $services = DB::all('SELECT s.name, COUNT(r.id) n, SUM(r.status IN ("confirmed","completed")) booked FROM services s LEFT JOIN service_requests r ON r.service_id = s.id AND r.created_at >= ?
                              WHERE s.tenant_id = ? GROUP BY s.id HAVING n > 0 ORDER BY n DESC LIMIT 10', [$since, $tid]);
        $patrols = (int) DB::value('SELECT COUNT(*) FROM patrol_logs WHERE tenant_id = ? AND created_at >= ?', [$tid, $since]);
        $this->render('reports', compact('days', 'byCategory', 'byStatus', 'response', 'arrival', 'apps', 'quotes', 'revenue', 'growth', 'services', 'patrols') + ['title' => 'Reports']);
    }

    /** CSV exports (open in Excel). */
    public function export(string $type): void
    {
        $tid = TenantContext::id();
        [$filename, $header, $rows] = match ($type) {
            'incidents' => ['incidents', ['Reference', 'Source', 'Category', 'Priority', 'Status', 'Occurred', 'Reported', 'Location', 'Resolved'],
                DB::all('SELECT i.reference, i.source, COALESCE(c.name, i.title), i.priority, i.status, i.occurred_at, i.created_at, i.location_text, i.resolved_at
                           FROM incidents i LEFT JOIN incident_categories c ON c.id = i.category_id WHERE i.tenant_id = ? ORDER BY i.id DESC', [$tid])],
            'payments' => ['payments', ['Reference', 'Customer', 'Quotation', 'Amount (R)', 'Method', 'Status', 'Paid at'],
                DB::all('SELECT pm.reference, CONCAT(p.first_name, " ", p.surname), q.reference, ROUND(pm.amount_cents/100, 2), pm.method, pm.status, pm.paid_at
                           FROM payments pm JOIN user_profiles p ON p.user_id = pm.customer_id LEFT JOIN quotations q ON q.id = pm.quotation_id WHERE pm.tenant_id = ? ORDER BY pm.id DESC', [$tid])],
            'applications' => ['applications', ['Reference', 'Type', 'Customer', 'Status', 'Submitted'],
                DB::all("SELECT a.reference, 'Residential', CONCAT(p.first_name, ' ', p.surname), a.status, a.submitted_at FROM property_applications a JOIN user_profiles p ON p.user_id = a.applicant_id WHERE a.tenant_id = ?
                         UNION ALL SELECT a.reference, 'Vehicle', CONCAT(p.first_name, ' ', p.surname), a.status, a.submitted_at FROM vehicle_applications a JOIN user_profiles p ON p.user_id = a.applicant_id WHERE a.tenant_id = ?", [$tid, $tid])],
            'homeowners' => ['homeowners', ['Name', 'Surname', 'Status', 'Type', 'Joined'],
                DB::all('SELECT p.first_name, p.surname, u.status, u.client_status, u.created_at FROM users u JOIN user_profiles p ON p.user_id = u.id JOIN roles r ON r.id = u.role_id
                          WHERE u.tenant_id = ? AND r.code = "homeowner" ORDER BY u.id DESC', [$tid])],
            default => throw new HttpException(404),
        };
        Audit::log('report.exported', 'report', $type);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // BOM so Excel reads UTF-8
        fputcsv($out, $header);
        foreach ($rows as $r) {
            // Neutralise spreadsheet formulas (CSV injection).
            fputcsv($out, array_map(fn($v) => is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'" . $v : $v, array_values($r)));
        }
        fclose($out);
    }
}
