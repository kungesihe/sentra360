<?php
declare(strict_types=1);

namespace App\Controllers\Tenant;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\DB;
use App\Core\HttpException;
use App\Core\TenantContext;
use App\Services\Ops;
use App\Services\Workflow;

/** Homeowner: properties, vehicles, and protection applications for them. */
final class HomeAssetsController extends Controller
{
    public const PROPERTY_SERVICES = ['Armed response', 'Alarm monitoring', 'Residential security', 'Security patrols', 'CCTV installation', 'Electric fencing', 'Access control', 'Security assessment'];
    public const VEHICLE_SERVICES = ['Vehicle tracking', 'Vehicle armed response', 'Vehicle recovery assistance', 'Escort to and from property'];
    public const PROPERTY_TYPES = ['House', 'Townhouse', 'Apartment / flat', 'Estate / complex', 'Farm / smallholding', 'Business premises', 'Other'];

    private function render(string $view, array $data): void
    {
        $this->view('home/' . $view, $data + ['user' => Auth::user()], 'tenant');
    }

    private function kindOr404(string $kind): string
    {
        Workflow::kind($kind);
        return $kind;
    }

    /** Extra service names configured by the company (active services), merged with the defaults. */
    private static function serviceOptions(string $kind): array
    {
        $base = $kind === 'property' ? self::PROPERTY_SERVICES : self::VEHICLE_SERVICES;
        return array_values(array_unique($base));
    }

    public function index(string $kind): void
    {
        $kind = $this->kindOr404($kind);
        [$appTable, $assetTable, $fk] = Workflow::kind($kind);
        $u = Auth::user();
        $assets = DB::all(
            "SELECT a.*, (SELECT ap.status FROM `$appTable` ap WHERE ap.$fk = a.id ORDER BY ap.id DESC LIMIT 1) AS app_status,
                    (SELECT ap.id FROM `$appTable` ap WHERE ap.$fk = a.id ORDER BY ap.id DESC LIMIT 1) AS app_id
               FROM `$assetTable` a WHERE a.tenant_id = ? AND a.owner_id = ? ORDER BY a.id DESC",
            [TenantContext::id(), $u['id']]
        );
        $this->render('assets', ['title' => $kind === 'property' ? 'My properties' : 'My vehicles', 'kind' => $kind, 'assets' => $assets]);
    }

    public function create(string $kind): void
    {
        $kind = $this->kindOr404($kind);
        $this->render('asset_form', [
            'title' => $kind === 'property' ? 'Register a property' : 'Register a vehicle', 'kind' => $kind,
            'serviceOptions' => self::serviceOptions($kind), 'types' => self::PROPERTY_TYPES,
        ]);
    }

    public function store(string $kind): void
    {
        $kind = $this->kindOr404($kind);
        $u = Auth::user();
        $tid = TenantContext::id();
        if ($kind === 'property') {
            $v = $this->validate([
                'name' => 'required,max:120', 'property_type' => 'required,max:40', 'address' => 'required,max:255',
                'suburb' => 'required,max:100', 'city' => 'required,max:100', 'existing_security' => 'max:2000',
                'access_requirements' => 'max:2000', 'security_concerns' => 'max:2000', 'additional_info' => 'max:2000',
            ]);
        } else {
            $v = $this->validate([
                'make' => 'required,max:60', 'model' => 'required,max:60', 'registration' => 'required,max:20',
                'colour' => 'required,max:40', 'year' => 'max:4', 'vin' => 'max:30', 'primary_driver' => 'max:120', 'additional_info' => 'max:2000',
            ]);
            if ($v->clean['year'] !== null && (!ctype_digit($v->clean['year']) || (int) $v->clean['year'] < 1950 || (int) $v->clean['year'] > (int) date('Y') + 1)) {
                $v->addError('year', 'Enter a valid year, e.g. 2019.');
            }
        }
        $services = array_values(array_intersect(self::serviceOptions($kind), (array) ($_POST['services'] ?? [])));
        $apply = !empty($_POST['apply']);
        if ($apply && !$services) {
            $v->addError('services', 'Choose at least one service to apply for.');
        }
        if ($v->fails()) {
            $this->backWithErrors("/account/assets/$kind/new", $v->errors);
        }
        [$appTable, $assetTable, $fk, $prefix] = Workflow::kind($kind);
        $data = $v->clean + ['tenant_id' => $tid, 'owner_id' => $u['id']];
        if ($kind === 'property') {
            [$data['latitude'], $data['longitude']] = HomeIncidentsController::coords();
        } else {
            $data['registration'] = strtoupper(preg_replace('/\s+/', ' ', $data['registration']));
            $data['year'] = $data['year'] !== null ? (int) $data['year'] : null;
        }
        $assetId = DB::insert($assetTable, $data);
        Ops::storeUploads($kind, $assetId, 'files', 5, ['image/png', 'image/jpeg', 'image/webp']);
        if ($apply) {
            $appId = self::createApplication($kind, $assetId, $services, trim((string) ($_POST['notes'] ?? '')) ?: null);
            flash('success', 'Saved and application submitted. We will review it and contact you.');
            redirect("/account/applications/$kind/$appId");
        }
        flash('success', ($kind === 'property' ? 'Property' : 'Vehicle') . ' saved. You can apply for protection at any time.');
        redirect("/account/assets/$kind");
    }

    private static function createApplication(string $kind, int $assetId, array $services, ?string $notes): int
    {
        [$appTable, , $fk, $prefix, $label] = Workflow::kind($kind);
        $u = Auth::user();
        $id = DB::insert($appTable, [
            'tenant_id' => TenantContext::id(), 'reference' => Ops::reference($prefix, $appTable), $fk => $assetId,
            'applicant_id' => $u['id'], 'services_requested' => json_encode($services), 'notes' => $notes,
            'status' => 'submitted', 'submitted_at' => now(),
        ]);
        Ops::event($kind . '_application', $id, null, 'submitted', 'Application submitted.');
        Ops::notifyStaff('admin', "New $label application", "{$u['first_name']} {$u['surname']} applied for: " . implode(', ', $services), "/admin/applications/$kind/$id");
        return $id;
    }

    public function apply(string $kind, string $id): void
    {
        $kind = $this->kindOr404($kind);
        [$appTable, $assetTable, $fk] = Workflow::kind($kind);
        $asset = Ops::requireOwnership(Workflow::asset($kind, (int) $id), (int) Auth::user()['id'], 'owner_id');
        $open = DB::value("SELECT id FROM `$appTable` WHERE $fk = ? AND status NOT IN ('rejected','cancelled','active')", [$asset['id']]);
        if ($open) {
            flash('info', 'There is already an open application for this ' . $kind . '.');
            redirect("/account/applications/$kind/$open");
        }
        $services = array_values(array_intersect(self::serviceOptions($kind), (array) ($_POST['services'] ?? [])));
        if (!$services) {
            $this->backWithErrors("/account/assets/$kind", ['services' => 'Choose at least one service.']);
        }
        $appId = self::createApplication($kind, (int) $asset['id'], $services, trim((string) ($_POST['notes'] ?? '')) ?: null);
        flash('success', 'Application submitted.');
        redirect("/account/applications/$kind/$appId");
    }

    public function application(string $kind, string $id): void
    {
        $kind = $this->kindOr404($kind);
        $app = Ops::requireOwnership(Workflow::application($kind, (int) $id), (int) Auth::user()['id'], 'applicant_id');
        [, , $fk] = Workflow::kind($kind);
        $this->render('application', [
            'title' => Workflow::KINDS[$kind][4] . ' application ' . $app['reference'], 'kind' => $kind, 'app' => $app,
            'asset' => Workflow::asset($kind, (int) $app[$fk]),
            'timeline' => Ops::timeline($kind . '_application', (int) $app['id'], false),
            'quotes' => DB::all('SELECT * FROM quotations WHERE tenant_id = ? AND subject_type = ? AND subject_id = ? AND status <> "draft" ORDER BY id DESC',
                [TenantContext::id(), $kind, $app['id']]),
            'serviceOptions' => self::serviceOptions($kind),
        ]);
    }

    public function applicationNote(string $kind, string $id): void
    {
        $kind = $this->kindOr404($kind);
        $app = Ops::requireOwnership(Workflow::application($kind, (int) $id), (int) Auth::user()['id'], 'applicant_id');
        $note = mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 2000);
        if ($note !== '') {
            Ops::event($kind . '_application', (int) $app['id'], $app['status'], $app['status'], 'Customer: ' . $note);
            Ops::notifyStaff('admin', "Message on {$app['reference']}", $note, "/admin/applications/$kind/{$app['id']}");
        }
        foreach (Ops::storeUploads($kind, (int) $app[Workflow::kind($kind)[2]], 'files', 5, ['image/png', 'image/jpeg', 'image/webp', 'application/pdf']) as $e) {
            flash('warning', 'Not attached: ' . $e);
        }
        flash('success', 'Sent to the company.');
        redirect("/account/applications/$kind/{$app['id']}");
    }

    public function applicationCancel(string $kind, string $id): void
    {
        $kind = $this->kindOr404($kind);
        $app = Ops::requireOwnership(Workflow::application($kind, (int) $id), (int) Auth::user()['id'], 'applicant_id');
        if (in_array($app['status'], ['active', 'cancelled', 'rejected'], true)) {
            throw new HttpException(400, 'This application can no longer be cancelled.');
        }
        Workflow::setApplicationStatus($kind, $app, 'cancelled', 'Cancelled by the customer.');
        redirect("/account/applications/$kind/{$app['id']}");
    }

    /** All applications (both kinds) for the dashboard / list page. */
    public static function myApplications(int $userId): array
    {
        $tid = TenantContext::id();
        return DB::all(
            "SELECT 'property' AS kind, a.id, a.reference, a.status, a.created_at, CONCAT(r.name, ', ', r.suburb) AS label
               FROM property_applications a JOIN registered_properties r ON r.id = a.property_id WHERE a.tenant_id = ? AND a.applicant_id = ?
             UNION ALL
             SELECT 'vehicle', a.id, a.reference, a.status, a.created_at, CONCAT(v.make, ' ', v.model, ' (', v.registration, ')')
               FROM vehicle_applications a JOIN registered_vehicles v ON v.id = a.vehicle_id WHERE a.tenant_id = ? AND a.applicant_id = ?
             ORDER BY created_at DESC",
            [$tid, $userId, $tid, $userId]
        );
    }

    public function applications(): void
    {
        $this->render('applications', ['title' => 'My applications', 'rows' => self::myApplications((int) Auth::user()['id'])]);
    }
}
