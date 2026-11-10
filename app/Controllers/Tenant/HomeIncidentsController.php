<?php
declare(strict_types=1);

namespace App\Controllers\Tenant;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\DB;
use App\Core\HttpException;
use App\Core\Notifier;
use App\Core\RateLimiter;
use App\Core\TenantContext;
use App\Services\Incidents;
use App\Services\Ops;

/** Homeowner: incident reports, panic button, emergency contacts. */
final class HomeIncidentsController extends Controller
{
    private function render(string $view, array $data): void
    {
        $this->view('home/' . $view, $data + ['user' => Auth::user()], 'tenant');
    }

    // ------------------------------------------------------------- incidents

    public function index(): void
    {
        $rows = DB::all(
            'SELECT i.*, c.name AS category_name FROM incidents i LEFT JOIN incident_categories c ON c.id = i.category_id
              WHERE i.tenant_id = ? AND i.reporter_id = ? ORDER BY i.id DESC',
            [TenantContext::id(), Auth::user()['id']]
        );
        $this->render('incidents', ['title' => 'My incident reports', 'rows' => $rows]);
    }

    public function create(): void
    {
        $u = Auth::user();
        $this->render('incident_form', [
            'title' => 'Report an incident',
            'categories' => DB::all('SELECT * FROM incident_categories ORDER BY sort_order'),
            'properties' => DB::all('SELECT id, name, address FROM registered_properties WHERE tenant_id = ? AND owner_id = ?', [TenantContext::id(), $u['id']]),
        ]);
    }

    public function store(): void
    {
        $u = Auth::user();
        RateLimiter::guard('incident:' . $u['id'], 20, 3600);
        $v = $this->validate([
            'category_id' => 'required', 'occurred_date' => 'required', 'occurred_time' => 'required',
            'location_text' => 'required,max:255', 'description' => 'required,max:5000',
            'witness_details' => 'max:2000', 'injuries' => 'max:2000',
        ]);
        $cat = DB::one('SELECT * FROM incident_categories WHERE id = ?', [(int) ($_POST['category_id'] ?? 0)]);
        if (!$cat) {
            $v->addError('category_id', 'Choose what happened.');
        }
        $when = strtotime(($v->clean['occurred_date'] ?? '') . ' ' . ($v->clean['occurred_time'] ?? ''));
        if (!$when || $when > time() + 300) {
            $v->addError('occurred_date', 'Enter a valid date and time (not in the future).');
        }
        [$lat, $lng] = self::coords();
        $propertyId = null;
        if (!empty($_POST['property_id'])) {
            $propertyId = (int) DB::value('SELECT id FROM registered_properties WHERE id = ? AND tenant_id = ? AND owner_id = ?', [(int) $_POST['property_id'], TenantContext::id(), $u['id']]) ?: null;
        }
        if ($v->fails()) {
            $this->backWithErrors('/account/incidents/new', $v->errors);
        }
        $inc = Incidents::create([
            'category_id' => $cat['id'], 'occurred_at' => date('Y-m-d H:i:s', $when),
            'location_text' => $v->clean['location_text'], 'latitude' => $lat, 'longitude' => $lng,
            'description' => $v->clean['description'], 'witness_details' => $v->clean['witness_details'],
            'injuries' => $v->clean['injuries'], 'assistance_required' => !empty($_POST['assistance_required']),
            'property_id' => $propertyId,
        ], (int) $u['id']);
        $errors = Ops::storeUploads('incident', (int) $inc['id']);
        foreach ($errors as $e) {
            flash('warning', 'Not attached: ' . $e);
        }
        flash('success', "Report {$inc['reference']} submitted. Submitting a report does not mean a response has been dispatched. You'll see each update here.");
        redirect('/account/incidents/' . $inc['id']);
    }

    public function show(string $id): void
    {
        $inc = Incidents::find((int) $id);
        Ops::requireOwnership($inc, (int) Auth::user()['id'], 'reporter_id');
        $this->render('incident', [
            'title' => 'Incident ' . $inc['reference'], 'inc' => $inc,
            'timeline' => Ops::timeline('incident', (int) $inc['id'], false),
            'files' => Ops::filesFor('incident', (int) $inc['id']),
            'assignments' => Incidents::assignments((int) $inc['id']),
        ]);
    }

    public function addInfo(string $id): void
    {
        $inc = Incidents::find((int) $id);
        Ops::requireOwnership($inc, (int) Auth::user()['id'], 'reporter_id');
        $note = trim((string) ($_POST['note'] ?? ''));
        if ($note !== '') {
            Ops::event('incident', (int) $inc['id'], $inc['status'], $inc['status'], 'Reporter added: ' . mb_substr($note, 0, 2000));
        }
        foreach (Ops::storeUploads('incident', (int) $inc['id']) as $e) {
            flash('warning', 'Not attached: ' . $e);
        }
        flash('success', 'Information added to your report.');
        redirect('/account/incidents/' . $inc['id']);
    }

    /** @return array{0:?float,1:?float} validated GPS coordinates from the form */
    public static function coords(): array
    {
        $lat = $_POST['latitude'] ?? '';
        $lng = $_POST['longitude'] ?? '';
        if (is_numeric($lat) && is_numeric($lng) && abs((float) $lat) <= 90 && abs((float) $lng) <= 180) {
            return [round((float) $lat, 7), round((float) $lng, 7)];
        }
        return [null, null];
    }

    // ----------------------------------------------------------------- panic

    public function panic(): void
    {
        $u = Auth::user();
        $tid = TenantContext::id();
        $active = DB::one('SELECT reference FROM panic_alerts WHERE tenant_id = ? AND customer_id = ? AND status NOT IN ("resolved","cancelled") ORDER BY id DESC LIMIT 1', [$tid, $u['id']]);
        $this->render('panic', [
            'title' => 'Panic button',
            'gaps' => Incidents::profileGaps($u),
            'eligible' => Incidents::panicEligible($u),
            'client' => Incidents::isClient($u),
            'active' => $active,
            'properties' => DB::all('SELECT id, name, address FROM registered_properties WHERE tenant_id = ? AND owner_id = ? ORDER BY protection_status = "active" DESC, id', [$tid, $u['id']]),
            'vehicles' => DB::all('SELECT id, make, model, registration FROM registered_vehicles WHERE tenant_id = ? AND owner_id = ? ORDER BY id', [$tid, $u['id']]),
            'contacts' => (int) DB::value('SELECT COUNT(*) FROM emergency_contacts WHERE tenant_id = ? AND user_id = ? AND notify_on_panic = 1', [$tid, $u['id']]),
        ]);
    }

    public function trigger(): void
    {
        $u = Auth::user();
        if (!Incidents::panicEligible($u)) {
            throw new HttpException(403, 'Complete your profile (home address and an emergency contact) to use the panic button.');
        }
        if (empty($_POST['confirm'])) {
            $this->backWithErrors('/account/panic', ['confirm' => 'Hold the button for 3 seconds, or tick the confirmation box, to send an alert.']);
        }
        RateLimiter::guard('panic:' . $u['id'], 10, 600);
        [$lat, $lng] = self::coords();
        $loc = (string) ($_POST['location_status'] ?? '');
        $alert = Incidents::triggerPanic($u, [
            'latitude' => $lat, 'longitude' => $lng,
            'accuracy_m' => is_numeric($_POST['accuracy'] ?? null) ? min(100000, (int) $_POST['accuracy']) : null,
            'location_status' => $lat !== null ? 'captured' : (in_array($loc, ['denied', 'unavailable'], true) ? $loc : 'unavailable'),
            'property_id' => (int) ($_POST['property_id'] ?? 0) ?: null, 'vehicle_id' => (int) ($_POST['vehicle_id'] ?? 0) ?: null,
            'silent' => !empty($_POST['silent']), 'extra_info' => mb_substr(trim((string) ($_POST['extra_info'] ?? '')), 0, 500) ?: null,
            'notify_contacts' => !empty($_POST['notify_contacts']),
        ]);
        redirect('/account/panic/' . $alert['reference']);
    }

    public function panicStatus(string $ref): void
    {
        $a = Incidents::panicForCustomer($ref, (int) Auth::user()['id']) ?? throw new HttpException(404);
        $contacts = $_SESSION['panic_contacts'] ?? null;
        unset($_SESSION['panic_contacts']);
        $this->render('panic_status', ['title' => 'Panic alert ' . $a['reference'], 'a' => $a, 'contacts' => $contacts]);
    }

    public function panicJson(string $ref): void
    {
        $a = Incidents::panicForCustomer($ref, (int) Auth::user()['id']) ?? throw new HttpException(404);
        $inc = $a['incident_id'] ? DB::one('SELECT status FROM incidents WHERE id = ?', [$a['incident_id']]) : null;
        $officer = DB::one('SELECT p.first_name, a.status FROM incident_assignments a JOIN user_profiles p ON p.user_id = a.officer_id WHERE a.incident_id = ? ORDER BY a.id DESC LIMIT 1', [$a['incident_id']]);
        $this->json([
            'status' => $a['status'], 'label' => Ops::label($a['status']), 'incident_status' => $inc['status'] ?? null,
            'submitted_at' => $a['created_at'], 'delivered_at' => $a['delivered_at'], 'acknowledged_at' => $a['acknowledged_at'],
            'dispatched_at' => $a['dispatched_at'], 'resolved_at' => $a['resolved_at'], 'cancelled_at' => $a['cancelled_at'],
            'callback_at' => $a['callback_at'], 'callback_method' => $a['callback_method'],
            'officer' => $officer ? ['name' => $officer['first_name'], 'status' => Ops::label($officer['status'])] : null,
        ]);
    }

    public function panicLocation(string $ref): void
    {
        $a = Incidents::panicForCustomer($ref, (int) Auth::user()['id']) ?? throw new HttpException(404);
        [$lat, $lng] = self::coords();
        if ($lat === null || in_array($a['status'], ['resolved', 'cancelled'], true)) {
            $this->json(['ok' => false]);
            return;
        }
        RateLimiter::guard('panic-loc:' . $a['id'], 120, 3600);
        DB::insert('panic_locations', ['tenant_id' => TenantContext::id(), 'panic_alert_id' => $a['id'], 'latitude' => $lat, 'longitude' => $lng,
            'accuracy_m' => is_numeric($_POST['accuracy'] ?? null) ? (int) $_POST['accuracy'] : null]);
        DB::update('panic_alerts', ['latitude' => $lat, 'longitude' => $lng, 'location_status' => 'captured'], ['id' => $a['id']]);
        DB::update('incidents', ['latitude' => $lat, 'longitude' => $lng], ['id' => $a['incident_id'], 'tenant_id' => TenantContext::id()]);
        $this->json(['ok' => true]);
    }

    public function panicCancel(string $ref): void
    {
        $a = Incidents::panicForCustomer($ref, (int) Auth::user()['id']) ?? throw new HttpException(404);
        if (!in_array($a['status'], ['resolved', 'cancelled'], true)) {
            DB::update('panic_alerts', ['status' => 'cancelled', 'cancelled_at' => now()], ['id' => $a['id']]);
            if ($inc = Incidents::find((int) $a['incident_id'])) {
                Incidents::setStatus($inc, 'resolved', 'Cancelled by the client: ' . (trim((string) ($_POST['reason'] ?? '')) ?: 'false alarm') . '.');
            }
            Ops::notifyStaff('security_staff', "Panic {$a['reference']} cancelled by client", 'The client cancelled the alert.', "/security/incidents/{$a['incident_id']}");
            flash('info', 'Your alert was cancelled and the control room was told.');
        }
        redirect('/account/panic/' . $a['reference']);
    }

    // --------------------------------------------------- emergency contacts

    public function contacts(): void
    {
        $rows = DB::all('SELECT * FROM emergency_contacts WHERE tenant_id = ? AND user_id = ? ORDER BY id', [TenantContext::id(), Auth::user()['id']]);
        $this->render('contacts', ['title' => 'Emergency contacts', 'rows' => $rows, 'smsReady' => Notifier::smsConfigured()]);
    }

    public function contactAdd(): void
    {
        $u = Auth::user();
        $v = $this->validate(['name' => 'required,max:120', 'relationship' => 'max:60', 'mobile' => 'required,mobile']);
        if ((int) DB::value('SELECT COUNT(*) FROM emergency_contacts WHERE tenant_id = ? AND user_id = ?', [TenantContext::id(), $u['id']]) >= 5) {
            $v->addError('name', 'You can save up to 5 emergency contacts.');
        }
        if ($v->fails()) {
            $this->backWithErrors('/account/contacts', $v->errors);
        }
        DB::insert('emergency_contacts', $v->clean + ['tenant_id' => TenantContext::id(), 'user_id' => $u['id'], 'notify_on_panic' => empty($_POST['notify_on_panic']) ? 0 : 1]);
        flash('success', 'Emergency contact saved.');
        redirect('/account/contacts');
    }

    public function contactDelete(string $id): void
    {
        DB::run('DELETE FROM emergency_contacts WHERE id = ? AND tenant_id = ? AND user_id = ?', [(int) $id, TenantContext::id(), Auth::user()['id']]);
        flash('success', 'Contact removed.');
        redirect('/account/contacts');
    }
}
