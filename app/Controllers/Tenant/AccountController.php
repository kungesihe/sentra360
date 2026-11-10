<?php
declare(strict_types=1);

namespace App\Controllers\Tenant;

use App\Controllers\Controller;
use App\Core\Audit;
use App\Core\Auth;
use App\Core\DB;
use App\Core\RateLimiter;
use App\Core\TenantContext;
use App\Services\UserService;
use App\Services\VerificationService;

/** Signed-in community member / client area. */
final class AccountController extends Controller
{
    public function dashboard(): void
    {
        $u = Auth::user();
        $tid = TenantContext::id();
        $uid = (int) $u['id'];
        $counts = [
            'incidents' => (int) DB::value('SELECT COUNT(*) FROM incidents WHERE tenant_id = ? AND reporter_id = ? AND source = "community"', [$tid, $uid]),
            'applications' => count(HomeAssetsController::myApplications($uid)),
            'quotations' => (int) DB::value('SELECT COUNT(*) FROM quotations WHERE tenant_id = ? AND customer_id = ? AND status = "sent"', [$tid, $uid]),
            'bookings' => (int) DB::value('SELECT COUNT(*) FROM service_requests WHERE tenant_id = ? AND customer_id = ? AND status NOT IN ("cancelled","rejected","completed")', [$tid, $uid]),
        ];
        $this->view('tenant/account/dashboard', [
            'title' => 'My account', 'user' => $u, 'counts' => $counts,
            'panicEligible' => \App\Services\Incidents::panicEligible($u),
            'gaps' => \App\Services\Incidents::profileGaps($u),
            'activePanic' => DB::one('SELECT reference, status FROM panic_alerts WHERE tenant_id = ? AND customer_id = ? AND status NOT IN ("resolved","cancelled") ORDER BY id DESC LIMIT 1', [$tid, $uid]),
            'alerts' => DB::all('SELECT * FROM safety_alerts WHERE tenant_id = ? AND (expires_at IS NULL OR expires_at > NOW()) ORDER BY id DESC LIMIT 3', [$tid]),
            'recentIncidents' => DB::all('SELECT id, reference, title, status, created_at FROM incidents WHERE tenant_id = ? AND reporter_id = ? ORDER BY id DESC LIMIT 4', [$tid, $uid]),
            'applications' => array_slice(HomeAssetsController::myApplications($uid), 0, 4),
            'pendingQuotes' => DB::all('SELECT id, reference, total_cents FROM quotations WHERE tenant_id = ? AND customer_id = ? AND status = "sent" ORDER BY id DESC', [$tid, $uid]),
            'verify' => (bool) config('require_verification'),
            'assets' => DB::all(
                "SELECT 'property' AS kind, r.id, CONCAT(r.name, ' · ', r.address, ', ', r.suburb) AS label, r.protection_status,
                        (SELECT a.status FROM property_applications a WHERE a.property_id = r.id ORDER BY a.id DESC LIMIT 1) AS app_status
                   FROM registered_properties r WHERE r.tenant_id = ? AND r.owner_id = ?
                 UNION ALL
                 SELECT 'vehicle', v.id, CONCAT(v.make, ' ', v.model, ' · ', v.registration), v.protection_status,
                        (SELECT a.status FROM vehicle_applications a WHERE a.vehicle_id = v.id ORDER BY a.id DESC LIMIT 1)
                   FROM registered_vehicles v WHERE v.tenant_id = ? AND v.owner_id = ?",
                [$tid, $uid, $tid, $uid]
            ),
            'protectionFrom' => DB::one('SELECT price_cents, price_unit FROM services WHERE tenant_id = ? AND is_active = 1 AND category = "Residential" AND pricing IN ("fixed","from") AND price_cents > 0 ORDER BY price_cents LIMIT 1', [$tid]),
        ], 'tenant');
    }

    public function verifyForm(): void
    {
        $u = Auth::user();
        if ($u['email_verified_at'] && $u['mobile_verified_at']) {
            redirect('/account');
        }
        $delivery = $_SESSION['delivery'] ?? null;
        unset($_SESSION['delivery']);
        $this->view('tenant/account/verify', ['title' => 'Verify your account', 'user' => $u, 'delivery' => $delivery], 'tenant');
    }

    public function verifyMobile(): void
    {
        $u = Auth::user();
        RateLimiter::guard('otp-check:' . $u['id'], 10, 900);
        $res = VerificationService::checkOtp($u, (string) ($_POST['code'] ?? ''));
        if ($res !== true) {
            $this->backWithErrors('/account/verify', ['code' => $res]);
        }
        DB::update('users', ['mobile_verified_at' => now()], ['id' => $u['id'], 'tenant_id' => TenantContext::id()]);
        UserService::refreshStatus((int) $u['id']);
        Audit::log('user.mobile_verified', 'user', $u['id']);
        flash('success', 'Your mobile number is verified.');
        redirect('/account/verify');
    }

    public function resendSms(): void
    {
        $u = Auth::user();
        RateLimiter::guard('otp-send:' . $u['id'], 3, 900);
        $_SESSION['delivery'] = ['sms' => VerificationService::sendMobileOtp($u, TenantContext::current())];
        redirect('/account/verify');
    }

    public function resendEmail(): void
    {
        $u = Auth::user();
        RateLimiter::guard('email-send:' . $u['id'], 3, 900);
        $_SESSION['delivery'] = ['email' => VerificationService::sendEmailVerification($u, TenantContext::current())];
        redirect('/account/verify');
    }

    public function profile(): void
    {
        $u = Auth::user();
        $sessions = DB::all(
            'SELECT id, ip_address, user_agent, created_at, last_seen_at FROM user_sessions
              WHERE user_type = "user" AND user_id = ? AND revoked_at IS NULL ORDER BY last_seen_at DESC',
            [$u['id']]
        );
        $consents = DB::all('SELECT consent_type, document_version, granted, created_at FROM consents WHERE user_id = ? AND tenant_id = ? ORDER BY id DESC', [$u['id'], TenantContext::id()]);
        $this->view('tenant/account/profile', ['title' => 'Profile & security', 'user' => $u, 'sessions' => $sessions, 'consents' => $consents], 'tenant');
    }

    public function profileSave(): void
    {
        $u = Auth::user();
        $v = $this->validate(['first_name' => 'required,max:100', 'surname' => 'required,max:100']);
        if ($v->fails()) {
            $this->backWithErrors('/account/profile', $v->errors);
        }
        DB::update('user_profiles', ['first_name' => $v->clean['first_name'], 'surname' => $v->clean['surname']], ['user_id' => $u['id'], 'tenant_id' => TenantContext::id()]);
        Audit::log('user.profile_updated', 'user', $u['id']);
        flash('success', 'Profile updated.');
        redirect('/account/profile');
    }

    public function passwordSave(): void
    {
        $u = Auth::user();
        RateLimiter::guard('pw-change:' . $u['id'], 5, 900);
        if (!password_verify((string) ($_POST['current_password'] ?? ''), $u['password_hash'])) {
            $this->backWithErrors('/account/profile', ['current_password' => 'Your current password is incorrect.']);
        }
        $pw = (string) ($_POST['password'] ?? '');
        if ($err = Auth::validatePassword($pw)) {
            $this->backWithErrors('/account/profile', ['password' => $err]);
        }
        if ($pw !== ($_POST['password_confirm'] ?? '')) {
            $this->backWithErrors('/account/profile', ['password_confirm' => 'Passwords do not match.']);
        }
        DB::update('users', ['password_hash' => password_hash($pw, PASSWORD_DEFAULT)], ['id' => $u['id'], 'tenant_id' => TenantContext::id()]);
        // Keep this session, sign out every other one.
        DB::run('UPDATE user_sessions SET revoked_at = NOW() WHERE user_id = ? AND token_hash <> ? AND revoked_at IS NULL',
            [$u['id'], hash('sha256', $_SESSION['auth']['token'])]);
        Audit::log('user.password_changed', 'user', $u['id']);
        flash('success', 'Password changed. Your other devices were signed out.');
        redirect('/account/profile');
    }

    /** Public page explaining account deletion (link required by Google Play). */
    public function deletionInfo(): void
    {
        $this->view('tenant/account/deletion', ['title' => 'Delete your account', 'user' => Auth::user()], 'tenant');
    }

    /** Signed-in homeowner asks for their account and data to be deleted. */
    public function deletionRequest(): void
    {
        $u = Auth::user();
        if (!$u || $u['portal'] !== 'homeowner') {
            redirect('/login');
        }
        if (empty($_POST['confirm'])) {
            $this->backWithErrors('/delete-account', ['confirm' => 'Tick the box to confirm you want your account deleted.']);
        }
        \App\Core\RateLimiter::guard('delete-req:' . $u['id'], 3, 86400);
        $reason = mb_substr(trim((string) ($_POST['reason'] ?? '')), 0, 500);
        Audit::log('user.deletion_requested', 'user', $u['id'], ['reason' => $reason]);
        \App\Services\Ops::notifyStaff('admin', 'Account deletion request', "{$u['first_name']} {$u['surname']} asked for their account and personal data to be deleted." . ($reason ? " Reason: $reason" : ''), "/admin/homeowners/{$u['id']}");
        flash('success', 'Your request has been sent. The company will delete your account and personal information within 30 days, except records the law requires them to keep, and will let you know.');
        redirect('/delete-account');
    }

    public function revokeSessions(): void
    {
        $u = Auth::user();
        Auth::revokeAll((int) $u['id']);
        Audit::log('user.sessions_revoked', 'user', $u['id']);
        Auth::logout();
        redirect('/login?signed_out=1');
    }
}
