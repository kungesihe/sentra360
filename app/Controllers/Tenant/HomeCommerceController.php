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
use App\Services\PayFast;
use App\Services\Workflow;

/** Homeowner: services marketplace, service requests/bookings, quotations, payments, notifications. */
final class HomeCommerceController extends Controller
{
    private function render(string $view, array $data): void
    {
        $this->view('home/' . $view, $data + ['user' => Auth::user()], 'tenant');
    }

    public static function activeServices(): array
    {
        return DB::all('SELECT * FROM services WHERE tenant_id = ? AND is_active = 1 ORDER BY category, sort_order, name', [TenantContext::id()]);
    }

    public static function service(int $id): array
    {
        return DB::one('SELECT * FROM services WHERE id = ? AND tenant_id = ? AND is_active = 1', [$id, TenantContext::id()]) ?? throw new HttpException(404);
    }

    // ---------------------------------------------------------- marketplace

    public function services(): void
    {
        $this->render('services', ['title' => 'Services', 'services' => self::activeServices()]);
    }

    /** Printable price list of every listed service (public and inside the app). */
    public function pricelist(): void
    {
        $this->render('pricelist', ['title' => 'Price list', 'services' => self::activeServices()]);
    }

    public function serviceShow(string $id): void
    {
        $s = self::service((int) $id);
        $this->render('service', ['title' => $s['name'], 's' => $s, 'photos' => Ops::filesFor('service', (int) $s['id'])]);
    }

    public function requestStore(string $id): void
    {
        $s = self::service((int) $id);
        $u = Auth::user();
        $v = $this->validate(['requested_date' => 'required', 'requested_time' => 'max:5', 'location_text' => 'required,max:255', 'duration_text' => 'max:100', 'instructions' => 'max:3000']);
        $date = \DateTime::createFromFormat('Y-m-d', (string) $v->clean['requested_date']);
        if (!$date || $date->format('Y-m-d') < date('Y-m-d')) {
            $v->addError('requested_date', 'Choose today or a future date.');
        }
        if ($v->clean['requested_time'] !== null && !preg_match('/^\d{2}:\d{2}$/', $v->clean['requested_time'])) {
            $v->addError('requested_time', 'Enter a time like 18:30.');
        }
        if ($v->fails()) {
            $this->backWithErrors("/account/services/{$s['id']}", $v->errors);
        }
        $rid = DB::insert('service_requests', $v->clean + [
            'tenant_id' => TenantContext::id(), 'reference' => Ops::reference('SRV', 'service_requests'),
            'service_id' => $s['id'], 'customer_id' => $u['id'], 'status' => 'submitted',
        ]);
        Ops::event('service_request', $rid, null, 'submitted', 'Request submitted.');
        Audit::log('service_request.created', 'service_request', $rid);
        Ops::notifyStaff('admin', "New service request: {$s['name']}", "{$u['first_name']} {$u['surname']} · {$v->clean['requested_date']} · {$v->clean['location_text']}", "/admin/requests/$rid");
        flash('success', 'Request sent. The company will confirm availability and pricing.');
        redirect("/account/requests/$rid");
    }

    public function requests(): void
    {
        $rows = DB::all('SELECT r.*, s.name AS service_name FROM service_requests r JOIN services s ON s.id = r.service_id
                          WHERE r.tenant_id = ? AND r.customer_id = ? ORDER BY r.id DESC', [TenantContext::id(), Auth::user()['id']]);
        $this->render('requests', ['title' => 'My service requests & bookings', 'rows' => $rows]);
    }

    private function myRequest(string $id): array
    {
        $r = DB::one('SELECT r.*, s.name AS service_name FROM service_requests r JOIN services s ON s.id = r.service_id WHERE r.id = ? AND r.tenant_id = ?', [(int) $id, TenantContext::id()]);
        return Ops::requireOwnership($r, (int) Auth::user()['id'], 'customer_id');
    }

    public function requestShow(string $id): void
    {
        $r = $this->myRequest($id);
        $this->render('request', [
            'title' => 'Request ' . $r['reference'], 'r' => $r,
            'timeline' => Ops::timeline('service_request', (int) $r['id'], false),
            'quotes' => DB::all('SELECT * FROM quotations WHERE tenant_id = ? AND subject_type = "service" AND subject_id = ? AND status <> "draft" ORDER BY id DESC', [TenantContext::id(), $r['id']]),
        ]);
    }

    public function requestCancel(string $id): void
    {
        $r = $this->myRequest($id);
        if (in_array($r['status'], ['completed', 'cancelled', 'rejected'], true)) {
            throw new HttpException(400);
        }
        DB::update('service_requests', ['status' => 'cancelled'], ['id' => $r['id']]);
        Ops::event('service_request', (int) $r['id'], $r['status'], 'cancelled', 'Cancelled by the customer.');
        Ops::notifyStaff('admin', "Request {$r['reference']} cancelled", 'The customer cancelled.', "/admin/requests/{$r['id']}");
        flash('info', 'Request cancelled.');
        redirect("/account/requests/{$r['id']}");
    }

    // ------------------------------------------------------------ quotations

    public function quotations(): void
    {
        $rows = DB::all('SELECT * FROM quotations WHERE tenant_id = ? AND customer_id = ? AND status <> "draft" ORDER BY id DESC', [TenantContext::id(), Auth::user()['id']]);
        $this->render('quotations', ['title' => 'My quotations', 'rows' => $rows]);
    }

    private function myQuote(string $id): array
    {
        $q = Workflow::quotation((int) $id);
        $q = Ops::requireOwnership($q, (int) Auth::user()['id'], 'customer_id');
        if ($q['status'] === 'draft') {
            throw new HttpException(404);
        }
        return $q;
    }

    public function quotation(string $id): void
    {
        $q = $this->myQuote($id);
        $this->render('quotation', [
            'title' => 'Quotation ' . $q['reference'], 'q' => $q,
            'items' => DB::all('SELECT * FROM quotation_items WHERE quotation_id = ? ORDER BY sort_order', [$q['id']]),
            'paid' => Workflow::paidCents((int) $q['id']),
            'payments' => DB::all('SELECT * FROM payments WHERE quotation_id = ? ORDER BY id', [$q['id']]),
            'subjectLink' => Workflow::subjectLink($q['subject_type'], (int) $q['subject_id'], false),
            'payfast' => PayFast::configured(), 'instructions' => DB::value('SELECT payment_instructions FROM tenant_settings WHERE tenant_id = ?', [TenantContext::id()]),
        ]);
    }

    public function quotationRespond(string $id): void
    {
        $q = $this->myQuote($id);
        $accept = ($_POST['decision'] ?? '') === 'accept';
        try {
            Workflow::respond($q, $accept, mb_substr(trim((string) ($_POST['reason'] ?? '')), 0, 500) ?: null);
            flash('success', $accept ? 'Quotation accepted. See below for how to pay.' : 'Quotation declined. The company has been told.');
        } catch (\RuntimeException $e) {
            flash('danger', $e->getMessage());
        }
        redirect("/account/quotations/{$q['id']}");
    }

    /** Start an online payment: create a pending payment and hand the customer to PayFast. */
    public function payOnline(string $id): void
    {
        $q = $this->myQuote($id);
        if (!PayFast::configured()) {
            throw new HttpException(400, 'Online payment is not available. Please use the payment instructions on the quotation.');
        }
        $due = (int) $q['total_cents'] - Workflow::paidCents((int) $q['id']);
        if ($q['status'] !== 'accepted' || $due <= 0) {
            throw new HttpException(400, 'Nothing to pay on this quotation.');
        }
        $u = Auth::user();
        $pid = DB::insert('payments', [
            'tenant_id' => TenantContext::id(), 'reference' => Ops::reference('PAY', 'payments'), 'customer_id' => $u['id'],
            'quotation_id' => $q['id'], 'amount_cents' => $due, 'method' => 'payfast', 'status' => 'pending',
        ]);
        $payment = DB::one('SELECT * FROM payments WHERE id = ?', [$pid]);
        $this->view('home/payfast_redirect', ['title' => 'Redirecting to PayFast', 'action' => PayFast::processUrl(),
            'fields' => PayFast::checkoutFields($payment, $u, "Quotation {$q['reference']}")], 'bare');
    }

    /** PayFast server-to-server notification (no session, no CSRF; verified by signature + PayFast validation). */
    public function payfastNotify(): void
    {
        $post = $_POST;
        $payment = DB::one('SELECT * FROM payments WHERE reference = ? AND tenant_id = ? AND method = "payfast"', [(string) ($post['m_payment_id'] ?? ''), TenantContext::id()]);
        if (!$payment) {
            http_response_code(404);
            return;
        }
        $err = PayFast::verifyItn($post, (int) $payment['amount_cents']);
        if ($err !== null) {
            Audit::log('payment.itn_rejected', 'payment', $payment['id'], ['reason' => $err], null, 'system');
            http_response_code(400);
            return;
        }
        if (($post['payment_status'] ?? '') === 'COMPLETE' && $payment['status'] !== 'paid') {
            $q = $payment['quotation_id'] ? DB::one('SELECT * FROM quotations WHERE id = ?', [$payment['quotation_id']]) : null;
            Workflow::recordPayment((int) $payment['customer_id'], $q, (int) $payment['amount_cents'], 'payfast', (string) ($post['pf_payment_id'] ?? ''), null, null, (int) $payment['id']);
        } elseif (($post['payment_status'] ?? '') === 'CANCELLED') {
            DB::update('payments', ['status' => 'failed'], ['id' => $payment['id']]);
        }
        http_response_code(200);
    }

    public function payments(): void
    {
        $rows = DB::all('SELECT p.*, q.reference AS quote_ref FROM payments p LEFT JOIN quotations q ON q.id = p.quotation_id
                          WHERE p.tenant_id = ? AND p.customer_id = ? ORDER BY p.id DESC', [TenantContext::id(), Auth::user()['id']]);
        $this->render('payments', ['title' => 'Payment history', 'rows' => $rows]);
    }

    // ---------------------------------------------------------- notifications

    public function notifications(): void
    {
        $u = Auth::user();
        $rows = DB::all('SELECT * FROM notifications WHERE user_id = ? AND channel = "in_app" ORDER BY id DESC LIMIT 100', [$u['id']]);
        DB::run('UPDATE notifications SET read_at = NOW() WHERE user_id = ? AND channel = "in_app" AND read_at IS NULL', [$u['id']]);
        $layout = $u['portal'] === 'homeowner' ? 'tenant' : 'portal';
        $this->view('home/notifications', ['title' => 'Notifications', 'rows' => $rows, 'user' => $u, 'me' => $u, 'portal' => $u['portal']], $layout);
    }
}
