<?php
declare(strict_types=1);

namespace App\Controllers\Tenant;

use App\Controllers\Controller;
use App\Core\Audit;
use App\Core\Auth;
use App\Core\DB;
use App\Core\HttpException;
use App\Core\TenantContext;
use App\Core\Upload;
use App\Services\UserService;

/** Admin portal (/admin): the company's office. Full control. */
final class AdminController extends Controller
{
    private function render(string $view, array $data): void
    {
        $this->view('admin/' . $view, $data + ['me' => Auth::user(), 'portal' => 'admin'], 'portal');
    }

    private static function roleId(string $code): int
    {
        return (int) DB::value('SELECT id FROM roles WHERE code = ?', [$code]);
    }

    public function dashboard(): void
    {
        $tid = TenantContext::id();
        $home = self::roleId('homeowner');
        $sec = self::roleId('security_staff');
        $stats = DB::one(
            'SELECT
                SUM(role_id = ?) AS homeowners,
                SUM(role_id = ? AND status = "active") AS verified,
                SUM(role_id = ? AND status = "pending") AS pending,
                SUM(role_id = ? AND client_status = "client") AS clients,
                SUM(role_id = ? AND created_at >= NOW() - INTERVAL 7 DAY) AS new_this_week,
                SUM(role_id = ?) AS security_staff
               FROM users WHERE tenant_id = ?',
            [$home, $home, $home, $home, $home, $sec, $tid]
        );
        $recent = DB::all(
            'SELECT u.id, u.status, u.created_at, p.first_name, p.surname FROM users u JOIN user_profiles p ON p.user_id = u.id
              WHERE u.tenant_id = ? AND u.role_id = ? ORDER BY u.id DESC LIMIT 6',
            [$tid, $home]
        );
        $ops = [
            'new_incidents' => (int) DB::value('SELECT COUNT(*) FROM incidents WHERE tenant_id = ? AND status = "submitted"', [$tid]),
            'open_incidents' => (int) DB::value('SELECT COUNT(*) FROM incidents WHERE tenant_id = ? AND status NOT IN ("resolved","closed")', [$tid]),
            'active_panics' => (int) DB::value('SELECT COUNT(*) FROM panic_alerts WHERE tenant_id = ? AND status NOT IN ("resolved","cancelled")', [$tid]),
            'panics_30' => (int) DB::value('SELECT COUNT(*) FROM panic_alerts WHERE tenant_id = ? AND created_at >= NOW() - INTERVAL 30 DAY', [$tid]),
            'prop_apps' => (int) DB::value('SELECT COUNT(*) FROM property_applications WHERE tenant_id = ? AND status NOT IN ("draft","active","rejected","cancelled")', [$tid]),
            'veh_apps' => (int) DB::value('SELECT COUNT(*) FROM vehicle_applications WHERE tenant_id = ? AND status NOT IN ("draft","active","rejected","cancelled")', [$tid]),
            'quotes_waiting' => (int) DB::value('SELECT COUNT(*) FROM quotations WHERE tenant_id = ? AND status = "sent"', [$tid]),
            'requests' => (int) DB::value('SELECT COUNT(*) FROM service_requests WHERE tenant_id = ? AND status IN ("submitted","under_review")', [$tid]),
            'paid_30' => (int) DB::value('SELECT COALESCE(SUM(amount_cents),0) FROM payments WHERE tenant_id = ? AND status = "paid" AND paid_at >= NOW() - INTERVAL 30 DAY', [$tid]),
            'active_props' => (int) DB::value('SELECT COUNT(*) FROM registered_properties WHERE tenant_id = ? AND protection_status = "active"', [$tid]),
            'active_vehicles' => (int) DB::value('SELECT COUNT(*) FROM registered_vehicles WHERE tenant_id = ? AND protection_status = "active"', [$tid]),
        ];
        $latestIncidents = DB::all('SELECT id, reference, title, priority, status, created_at FROM incidents WHERE tenant_id = ? ORDER BY id DESC LIMIT 6', [$tid]);
        $this->render('dashboard', ['title' => 'Dashboard', 'stats' => $stats, 'recent' => $recent, 'ops' => $ops, 'latestIncidents' => $latestIncidents]);
    }

    // ------------------------------------------------------------- branding

    public function branding(): void
    {
        $this->render('branding', ['title' => 'Branding & company profile', 'tenant' => TenantContext::current()]);
    }

    public function brandingSave(): void
    {
        $tid = TenantContext::id();
        $v = $this->validate([
            'display_name' => 'required,max:150', 'welcome_message' => 'max:255', 'about' => 'max:3000',
            'services_offered' => 'max:3000', 'primary_color' => 'required,color', 'secondary_color' => 'required,color',
            'contact_phone' => 'max:30', 'contact_email' => 'email,max:190', 'physical_address' => 'max:255',
            'emergency_phone' => 'max:30', 'emergency_notes' => 'max:500', 'terms_text' => 'max:60000', 'privacy_text' => 'max:60000',
            'payment_instructions' => 'max:3000',
        ]);
        $logo = Upload::imageData($_FILES['logo'] ?? []);
        if (isset($logo['error'])) {
            $v->addError('logo', $logo['error']);
        }
        if ($v->fails()) {
            $this->backWithErrors('/admin/branding', $v->errors);
        }
        $data = $v->clean;
        if (isset($logo['data'])) {
            // Stored in the database (survives restarts on temporary-disk hosts); served by /logo.
            $data['logo_data'] = $logo['data'];
            $data['logo_mime'] = $logo['mime'];
            $data['logo_path'] = '/logo?v=' . substr(sha1($logo['data']), 0, 12);
        } elseif (!empty($_POST['remove_logo'])) {
            $data['logo_path'] = null;
            $data['logo_data'] = null;
            $data['logo_mime'] = null;
        }
        DB::update('tenant_settings', $data, ['tenant_id' => $tid]);
        DB::update('tenants', ['name' => $data['display_name']], ['id' => $tid]);
        Audit::log('company.branding_updated', 'company', $tid, ['fields' => array_values(array_diff(array_keys($data), ['logo_data']))]);
        flash('success', 'Branding saved. The public site has been updated.');
        redirect('/admin/branding');
    }

    // ----------------------------------------------------------- homeowners

    public function members(): void
    {
        $this->render('members', ['title' => 'Homeowners'] + self::homeownerList());
    }

    /** Shared by the admin and security portals. */
    public static function homeownerList(): array
    {
        $q = trim((string) ($_GET['q'] ?? ''));
        $status = (string) ($_GET['status'] ?? '');
        $sql = 'SELECT u.id, u.email, u.mobile, u.status, u.client_status, u.created_at, u.email_verified_at, u.mobile_verified_at,
                       p.first_name, p.surname
                  FROM users u JOIN user_profiles p ON p.user_id = u.id
                 WHERE u.tenant_id = ? AND u.role_id = ?';
        $params = [TenantContext::id(), self::roleId('homeowner')];
        if ($q !== '') {
            $sql .= ' AND (p.first_name LIKE ? OR p.surname LIKE ? OR u.email LIKE ? OR u.mobile LIKE ?)';
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';
            array_push($params, $like, $like, $like, $like);
        }
        if (in_array($status, ['pending', 'active', 'suspended'], true)) {
            $sql .= ' AND u.status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY u.id DESC LIMIT 200';
        return ['members' => DB::all($sql, $params), 'q' => $q, 'status' => $status];
    }

    /** Load a homeowner (404 for staff or unknown ids) and log the access. */
    public static function homeownerRecord(string $id): array
    {
        $tid = TenantContext::id();
        $m = UserService::find($tid, (int) $id);
        if (!$m || $m['role_code'] !== 'homeowner') {
            throw new HttpException(404);
        }
        Audit::log('homeowner.viewed', 'user', $m['id']);
        $m['consents'] = DB::all('SELECT * FROM consents WHERE user_id = ? AND tenant_id = ? ORDER BY id DESC', [$m['id'], $tid]);
        return $m;
    }

    public function member(string $id): void
    {
        $m = self::homeownerRecord($id);
        $this->render('member', ['title' => $m['first_name'] . ' ' . $m['surname'], 'm' => $m]);
    }

    public function memberStatus(string $id): void
    {
        $tid = TenantContext::id();
        $m = UserService::find($tid, (int) $id);
        if (!$m || $m['role_code'] !== 'homeowner') {
            throw new HttpException(404);
        }
        $action = (string) ($_POST['action'] ?? '');
        $changes = match ($action) {
            'suspend'    => ['status' => 'suspended'],
            'reactivate' => ['status' => ($m['email_verified_at'] && $m['mobile_verified_at']) ? 'active' : 'pending'],
            'verify'     => ['status' => 'active', 'email_verified_at' => $m['email_verified_at'] ?? now(), 'mobile_verified_at' => $m['mobile_verified_at'] ?? now()],
            'make_client'    => ['client_status' => 'client'],
            'remove_client'  => ['client_status' => 'community'],
            default      => throw new HttpException(400),
        };
        DB::update('users', $changes, ['id' => $m['id'], 'tenant_id' => $tid]);
        if ($action === 'suspend') {
            Auth::revokeAll((int) $m['id']);
        }
        Audit::log('homeowner.' . $action, 'user', $m['id'], ['note' => mb_substr((string) ($_POST['note'] ?? ''), 0, 255)]);
        flash('success', 'Homeowner updated.');
        redirect('/admin/homeowners/' . $m['id']);
    }

    // ---------------------------------------------------------------- staff

    public function staff(): void
    {
        $staff = DB::all(
            'SELECT u.id, u.email, u.mobile, u.status, u.last_login_at, r.code AS role_code, r.name AS role_name, p.first_name, p.surname
               FROM users u JOIN roles r ON r.id = u.role_id JOIN user_profiles p ON p.user_id = u.id
              WHERE u.tenant_id = ? AND r.is_staff = 1 ORDER BY r.code, p.first_name',
            [TenantContext::id()]
        );
        $this->render('staff', ['title' => 'Admin & security logins', 'staff' => $staff]);
    }

    public function staffCreate(): void
    {
        $tid = TenantContext::id();
        $v = $this->validate([
            'first_name' => 'required,max:100', 'surname' => 'required,max:100', 'email' => 'required,email,max:190',
            'mobile' => 'required,mobile', 'role' => 'required,in:admin|security_staff', 'password' => 'required',
        ]);
        $pw = (string) ($_POST['password'] ?? '');
        if ($err = Auth::validatePassword($pw)) {
            $v->addError('password', $err);
        }
        if (!$v->fails()) {
            if (UserService::emailTaken($tid, $v->clean['email'])) {
                $v->addError('email', 'This email is already used by another account.');
            }
            if (UserService::mobileTaken($tid, $v->clean['mobile'])) {
                $v->addError('mobile', 'This mobile number is already used by another account.');
            }
        }
        if ($v->fails()) {
            $this->backWithErrors('/admin/staff', $v->errors);
        }
        $id = UserService::create($tid, self::roleId($v->clean['role']), array_merge($v->clean, ['password' => $pw]), 'active');
        Audit::log('staff.created', 'user', $id, ['role' => $v->clean['role']]);
        flash('success', 'Login created. Share the temporary password securely and ask them to change it after signing in.');
        redirect('/admin/staff');
    }

    public function staffStatus(string $id): void
    {
        $tid = TenantContext::id();
        $s = UserService::find($tid, (int) $id);
        if (!$s || !$s['is_staff']) {
            throw new HttpException(404);
        }
        if ((int) $s['id'] === (int) Auth::user()['id']) {
            flash('danger', 'You cannot disable your own login.');
            redirect('/admin/staff');
        }
        $action = (string) ($_POST['action'] ?? '');
        $status = match ($action) {
            'disable' => 'disabled',
            'enable'  => 'active',
            default   => throw new HttpException(400),
        };
        DB::update('users', ['status' => $status], ['id' => $s['id'], 'tenant_id' => $tid]);
        if ($status === 'disabled') {
            Auth::revokeAll((int) $s['id']);
        }
        Audit::log('staff.' . $action, 'user', $s['id']);
        flash('success', 'Login updated.');
        redirect('/admin/staff');
    }

    public function profile(): void
    {
        $this->view('security/profile', ['title' => 'My login', 'action' => '/admin/password', 'me' => Auth::user(), 'portal' => 'admin'], 'portal');
    }

    public function passwordSave(): void
    {
        SecurityController::changePassword($this, '/admin/profile');
    }

    public function audit(): void
    {
        $logs = DB::all(
            'SELECT a.*, p.first_name, p.surname FROM audit_logs a
               LEFT JOIN users u ON a.actor_type = "user" AND u.id = a.actor_id
               LEFT JOIN user_profiles p ON p.user_id = u.id
              WHERE a.tenant_id = ? ORDER BY a.id DESC LIMIT 200',
            [TenantContext::id()]
        );
        $this->render('audit', ['title' => 'Audit log', 'logs' => $logs]);
    }
}
