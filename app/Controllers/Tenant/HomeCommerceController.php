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

/** Homeowner: services marketplace, service requests/bookings, quotations, payments, notifications. */
final class HomeCommerceController extends Controller
{
    private function render(string $view, array $data): void
    {
        $this->view('home/' . $view, $data + ['user' => Auth::user()], 'tenant');
    }

    /**
     * Services a customer can book on their own. Plans are signed up for from the protection
     * list, and monthly add-ons are added to a plan, so neither is booked here (no duplicates).
     */
    public static function activeServices(): array
    {
        return DB::all('SELECT * FROM services WHERE tenant_id = ? AND is_active = 1 AND plan_for IS NULL AND category <> "Add-ons" ORDER BY category, sort_order, name', [TenantContext::id()]);
    }

    public static function service(int $id): array
    {
        return DB::one('SELECT * FROM services WHERE id = ? AND tenant_id = ? AND is_active = 1 AND plan_for IS NULL AND category <> "Add-ons"', [$id, TenantContext::id()]) ?? throw new HttpException(404);
    }

    // ---------------------------------------------------------- marketplace

    public function services(): void
    {
        $this->render('services', ['title' => 'Services', 'services' => self::activeServices()]);
    }

    /** Printable price list: plans, add-ons and once-off services (public and inside the app). */
    public function pricelist(): void
    {
        $all = DB::all('SELECT * FROM services WHERE tenant_id = ? AND is_active = 1 AND plan_for IS NULL ORDER BY category, sort_order, name', [TenantContext::id()]);
        $this->render('pricelist', ['title' => 'Price list', 'services' => $all]);
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
            'instructions' => DB::value('SELECT payment_instructions FROM tenant_settings WHERE tenant_id = ?', [TenantContext::id()]),
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

    /**
     * The company does not process payments: customers pay by EFT and upload their proof of
     * payment here. The Admin checks it against the bank statement and approves or rejects it.
     */
    public function uploadProof(string $id): void
    {
        $q = $this->myQuote($id);
        $due = (int) $q['total_cents'] - Workflow::paidCents((int) $q['id']);
        $back = "/account/quotations/{$q['id']}";
        if ($q['status'] !== 'accepted' || $due <= 0) {
            $this->backWithErrors($back, ['pop' => 'There is nothing to pay on this quotation.']);
        }
        $cents = Ops::cents((string) ($_POST['amount'] ?? ''));
        if (!$cents || $cents <= 0) {
            $this->backWithErrors($back, ['amount' => 'Enter the amount you paid, e.g. 450.00.']);
        }
        if (empty($_FILES['pop']['name'][0])) {
            $this->backWithErrors($back, ['pop' => 'Attach your proof of payment (PDF or photo).']);
        }
        $u = Auth::user();
        $pid = DB::insert('payments', [
            'tenant_id' => TenantContext::id(), 'reference' => Ops::reference('PAY', 'payments'), 'customer_id' => $u['id'],
            'quotation_id' => $q['id'], 'amount_cents' => $cents, 'method' => 'eft', 'status' => 'pending',
            'provider_reference' => mb_substr(trim((string) ($_POST['bank_reference'] ?? '')), 0, 100) ?: null,
        ]);
        $errors = Ops::storeUploads('payment', $pid, 'pop', 1, ['application/pdf', 'image/png', 'image/jpeg', 'image/webp']);
        if (!Ops::filesFor('payment', $pid)) {
            DB::run('DELETE FROM payments WHERE id = ?', [$pid]);
            $this->backWithErrors($back, ['pop' => $errors[0] ?? 'The file could not be saved. Use a PDF, JPG or PNG under the size limit.']);
        }
        Audit::log('payment.proof_uploaded', 'payment', $pid, ['amount' => $cents]);
        Ops::notifyStaff('admin', 'Proof of payment to check', "{$u['first_name']} {$u['surname']} uploaded proof of payment of " . money_exact($cents) . " for {$q['reference']}.", '/admin/payments');
        flash('success', 'Thank you. Your proof of payment was sent and we will confirm it shortly.');
        redirect($back);
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
