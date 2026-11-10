<?php
declare(strict_types=1);

namespace App\Controllers\Tenant;

use App\Controllers\Controller;
use App\Core\Audit;
use App\Core\Auth;
use App\Core\DB;
use App\Core\HttpException;
use App\Core\TenantContext;
use App\Services\Incidents;
use App\Services\Ops;

/**
 * Incident management for staff, shared by the Admin (/admin/incidents) and
 * Security (/security/incidents) portals, plus the live control room feed.
 */
final class StaffIncidentsController extends Controller
{
    private function portal(): string
    {
        return str_starts_with(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '', '/admin') ? 'admin' : 'security';
    }

    private function render(string $view, array $data): void
    {
        $this->view($view, $data + ['me' => Auth::user(), 'portal' => $this->portal(), 'base' => '/' . $this->portal()], 'portal');
    }

    public static function officers(): array
    {
        return DB::all(
            'SELECT u.id, p.first_name, p.surname,
                    (SELECT COUNT(*) FROM incident_assignments a WHERE a.officer_id = u.id AND a.status NOT IN ("completed","declined")) AS open_jobs
               FROM users u JOIN roles r ON r.id = u.role_id JOIN user_profiles p ON p.user_id = u.id
              WHERE u.tenant_id = ? AND r.code = "security_staff" AND u.status = "active" ORDER BY p.first_name',
            [TenantContext::id()]
        );
    }

    public function index(): void
    {
        $tid = TenantContext::id();
        $f = [
            'q' => trim((string) ($_GET['q'] ?? '')), 'status' => (string) ($_GET['status'] ?? ''),
            'category' => (string) ($_GET['category'] ?? ''), 'priority' => (string) ($_GET['priority'] ?? ''),
            'from' => (string) ($_GET['from'] ?? ''), 'to' => (string) ($_GET['to'] ?? ''),
        ];
        $sql = 'SELECT i.*, c.name AS category_name, p.first_name, p.surname FROM incidents i
                  LEFT JOIN incident_categories c ON c.id = i.category_id LEFT JOIN user_profiles p ON p.user_id = i.reporter_id
                 WHERE i.tenant_id = ?';
        $params = [$tid];
        if ($f['q'] !== '') {
            $sql .= ' AND (i.reference LIKE ? OR i.title LIKE ? OR i.location_text LIKE ? OR i.description LIKE ?)';
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $f['q']) . '%';
            array_push($params, $like, $like, $like, $like);
        }
        if (in_array($f['status'], Incidents::STATUSES, true)) {
            $sql .= ' AND i.status = ?';
            $params[] = $f['status'];
        } elseif ($f['status'] === 'open') {
            $sql .= ' AND i.status NOT IN ("resolved","closed")';
        }
        if (ctype_digit($f['category'])) {
            $sql .= ' AND i.category_id = ?';
            $params[] = (int) $f['category'];
        }
        if (in_array($f['priority'], ['low', 'normal', 'high', 'critical'], true)) {
            $sql .= ' AND i.priority = ?';
            $params[] = $f['priority'];
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['from'])) {
            $sql .= ' AND i.created_at >= ?';
            $params[] = $f['from'] . ' 00:00:00';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['to'])) {
            $sql .= ' AND i.created_at <= ?';
            $params[] = $f['to'] . ' 23:59:59';
        }
        $sql .= ' ORDER BY FIELD(i.priority,"critical","high","normal","low"), i.id DESC LIMIT 300';
        $this->render('staff/incidents', [
            'title' => 'Incidents', 'rows' => DB::all($sql, $params), 'f' => $f,
            'categories' => DB::all('SELECT id, name FROM incident_categories ORDER BY sort_order'),
        ]);
    }

    public function create(): void
    {
        $this->render('staff/incident_new', ['title' => 'Log an incident', 'categories' => DB::all('SELECT * FROM incident_categories ORDER BY sort_order')]);
    }

    public function store(): void
    {
        $v = $this->validate(['category_id' => 'required', 'location_text' => 'required,max:255', 'description' => 'required,max:5000']);
        $cat = DB::one('SELECT id FROM incident_categories WHERE id = ?', [(int) ($_POST['category_id'] ?? 0)]);
        if (!$cat) {
            $v->addError('category_id', 'Choose a category.');
        }
        if ($v->fails()) {
            $this->backWithErrors('/' . $this->portal() . '/incidents/new', $v->errors);
        }
        [$lat, $lng] = HomeIncidentsController::coords();
        $inc = Incidents::create(['category_id' => $cat['id'], 'location_text' => $v->clean['location_text'], 'description' => $v->clean['description'],
            'latitude' => $lat, 'longitude' => $lng, 'assistance_required' => !empty($_POST['assistance_required'])], null, 'staff');
        Ops::storeUploads('incident', (int) $inc['id']);
        redirect('/' . $this->portal() . '/incidents/' . $inc['id']);
    }

    public function show(string $id): void
    {
        $inc = Incidents::find((int) $id) ?? throw new HttpException(404);
        $inc = Incidents::markReceived($inc);
        Audit::log('incident.viewed', 'incident', $inc['id']);
        $panic = DB::one('SELECT * FROM panic_alerts WHERE incident_id = ?', [$inc['id']]);
        $asset = null;
        if ($inc['property_id']) {
            $asset = DB::one('SELECT name, address, suburb, city, access_requirements FROM registered_properties WHERE id = ? AND tenant_id = ?', [$inc['property_id'], TenantContext::id()]);
        }
        $vehicle = $inc['vehicle_id'] ? DB::one('SELECT make, model, colour, registration FROM registered_vehicles WHERE id = ? AND tenant_id = ?', [$inc['vehicle_id'], TenantContext::id()]) : null;
        $me = Auth::user();
        $this->render('staff/incident', [
            'title' => $inc['reference'], 'inc' => $inc, 'panic' => $panic, 'asset' => $asset, 'vehicle' => $vehicle,
            'timeline' => Ops::timeline('incident', (int) $inc['id'], true),
            'files' => Ops::filesFor('incident', (int) $inc['id']),
            'assignments' => Incidents::assignments((int) $inc['id']),
            'myAssignment' => DB::one('SELECT * FROM incident_assignments WHERE incident_id = ? AND officer_id = ? AND status NOT IN ("completed","declined") ORDER BY id DESC LIMIT 1', [$inc['id'], $me['id']]),
            'officers' => self::officers(),
            'trail' => $panic ? DB::all('SELECT latitude, longitude, created_at FROM panic_locations WHERE panic_alert_id = ? ORDER BY id', [$panic['id']]) : [],
            'contacts' => $panic ? DB::all('SELECT name, relationship, mobile FROM emergency_contacts WHERE tenant_id = ? AND user_id = ?', [TenantContext::id(), $inc['reporter_id']]) : [],
        ]);
    }

    private function load(string $id): array
    {
        return Incidents::find((int) $id) ?? throw new HttpException(404);
    }

    private function back(array $inc): never
    {
        redirect('/' . $this->portal() . '/incidents/' . $inc['id']);
    }

    public function status(string $id): void
    {
        $inc = $this->load($id);
        $status = (string) ($_POST['status'] ?? '');
        $note = mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 2000) ?: null;
        if (in_array($status, ['resolved', 'closed'], true)) {
            $res = mb_substr(trim((string) ($_POST['resolution'] ?? '')), 0, 2000);
            if ($res === '') {
                flash('danger', 'Describe the outcome before resolving.');
                $this->back($inc);
            }
            DB::update('incidents', ['resolution' => $res], ['id' => $inc['id'], 'tenant_id' => TenantContext::id()]);
            $note = $note ?? $res;
        }
        Incidents::setStatus($inc, $status, $note);
        flash('success', 'Status updated.');
        $this->back($inc);
    }

    public function note(string $id): void
    {
        $inc = $this->load($id);
        $note = mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 2000);
        $internal = !empty($_POST['internal']);
        if ($note !== '') {
            Ops::event('incident', (int) $inc['id'], $inc['status'], $inc['status'], $note, $internal);
            if (!$internal && $inc['reporter_id']) {
                Ops::notify((int) $inc['reporter_id'], "Update on {$inc['reference']}", $note, "/account/incidents/{$inc['id']}");
            }
        }
        foreach (Ops::storeUploads('incident', (int) $inc['id']) as $e) {
            flash('warning', 'Not attached: ' . $e);
        }
        flash('success', $internal ? 'Internal note added.' : 'Update added and shared with the reporter.');
        $this->back($inc);
    }

    public function assign(string $id): void
    {
        $inc = $this->load($id);
        try {
            Incidents::assign($inc, (int) ($_POST['officer_id'] ?? 0));
            flash('success', 'Officer assigned.');
        } catch (\RuntimeException $e) {
            if ($e instanceof HttpException) {
                throw $e;
            }
            flash('danger', $e->getMessage());
        }
        $this->back($inc);
    }

    /** The signed-in officer updates their own assignment. */
    public function response(string $id): void
    {
        $inc = $this->load($id);
        $me = Auth::user();
        $a = DB::one('SELECT * FROM incident_assignments WHERE incident_id = ? AND officer_id = ? AND status NOT IN ("completed","declined") ORDER BY id DESC LIMIT 1', [$inc['id'], $me['id']])
            ?? throw new HttpException(403, 'This incident is not assigned to you.');
        Incidents::officerUpdate($a, $inc, (string) ($_POST['status'] ?? ''), mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 1000) ?: null);
        flash('success', 'Response updated.');
        $this->back($inc);
    }

    public function acknowledge(string $id): void
    {
        $inc = $this->load($id);
        $panic = DB::one('SELECT * FROM panic_alerts WHERE incident_id = ? AND tenant_id = ?', [$inc['id'], TenantContext::id()]);
        if ($panic) {
            Incidents::acknowledgePanic($panic);
        } elseif ($inc['status'] === 'submitted') {
            Incidents::markReceived($inc);
        }
        if (($_POST['return'] ?? '') === 'feed') {
            redirect('/security');
        }
        $this->back($inc);
    }

    // ------------------------------------------------------------ control room

    public function controlRoom(): void
    {
        $this->render('staff/control_room', ['title' => 'Control room', 'officers' => self::officers()]);
    }

    /** JSON feed polled by the control room screen every few seconds. */
    public function feed(): void
    {
        $tid = TenantContext::id();
        $panics = DB::all(
            'SELECT pa.id, pa.reference, pa.status, pa.created_at, pa.latitude, pa.longitude, pa.accuracy_m, pa.location_status, pa.silent, pa.extra_info,
                    pa.incident_id, p.first_name, p.surname, u.mobile, rp.name AS property_name, rp.address, rp.suburb
               FROM panic_alerts pa JOIN users u ON u.id = pa.customer_id JOIN user_profiles p ON p.user_id = u.id
               LEFT JOIN registered_properties rp ON rp.id = pa.property_id
              WHERE pa.tenant_id = ? AND pa.status NOT IN ("resolved","cancelled") ORDER BY pa.id DESC',
            [$tid]
        );
        Incidents::markDelivered(array_map(fn($p) => (int) $p['id'], array_filter($panics, fn($p) => $p['status'] === 'submitted')));
        $incidents = DB::all(
            'SELECT i.id, i.reference, i.title, i.priority, i.status, i.location_text, i.latitude, i.longitude, i.created_at, i.source,
                    (SELECT GROUP_CONCAT(p.first_name SEPARATOR ", ") FROM incident_assignments a JOIN user_profiles p ON p.user_id = a.officer_id
                      WHERE a.incident_id = i.id AND a.status NOT IN ("completed","declined")) AS officers
               FROM incidents i WHERE i.tenant_id = ? AND i.status NOT IN ("resolved","closed") AND i.source <> "panic"
              ORDER BY FIELD(i.priority,"critical","high","normal","low"), i.id DESC LIMIT 100',
            [$tid]
        );
        $me = Auth::user();
        $mine = (int) DB::value('SELECT COUNT(*) FROM incident_assignments WHERE tenant_id = ? AND officer_id = ? AND status NOT IN ("completed","declined")', [$tid, $me['id']]);
        $clean = fn(array $rows) => array_map(function ($r) {
            foreach ($r as $k => $v) {
                if (is_string($v)) {
                    $r[$k] = $v; // JSON-encoded; escaped in the browser with textContent
                }
            }
            $r['status_label'] = Ops::label($r['status']);
            return $r;
        }, $rows);
        $this->json(['now' => date('H:i:s'), 'panics' => $clean($panics), 'incidents' => $clean($incidents), 'my_assignments' => $mine]);
    }

    public function myAssignments(): void
    {
        $rows = DB::all(
            'SELECT a.*, i.reference, i.title, i.priority, i.location_text, i.status AS incident_status, i.source FROM incident_assignments a
               JOIN incidents i ON i.id = a.incident_id WHERE a.tenant_id = ? AND a.officer_id = ? ORDER BY a.status IN ("completed","declined"), a.id DESC LIMIT 100',
            [TenantContext::id(), Auth::user()['id']]
        );
        $this->render('staff/assignments', ['title' => 'My assignments', 'rows' => $rows]);
    }

    // ---------------------------------------------------------------- patrols

    public function patrols(): void
    {
        $rows = DB::all(
            'SELECT l.*, p.first_name, p.surname FROM patrol_logs l JOIN user_profiles p ON p.user_id = l.officer_id
              WHERE l.tenant_id = ? ORDER BY l.id DESC LIMIT 200',
            [TenantContext::id()]
        );
        $this->render('staff/patrols', ['title' => 'Patrol log', 'rows' => $rows, 'canLog' => $this->portal() === 'security']);
    }

    public function patrolStore(): void
    {
        $v = $this->validate(['area' => 'required,max:150', 'checkpoint' => 'max:150', 'notes' => 'max:2000']);
        if ($v->fails()) {
            $this->backWithErrors('/security/patrols', $v->errors);
        }
        [$lat, $lng] = HomeIncidentsController::coords();
        DB::insert('patrol_logs', $v->clean + ['tenant_id' => TenantContext::id(), 'officer_id' => Auth::user()['id'], 'latitude' => $lat, 'longitude' => $lng]);
        flash('success', 'Patrol check-in logged.');
        redirect('/security/patrols');
    }
}
