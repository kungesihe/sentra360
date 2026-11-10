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
        $this->view('tenant/account/dashboard', ['title' => 'My account', 'user' => $u], 'tenant');
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

    public function revokeSessions(): void
    {
        $u = Auth::user();
        Auth::revokeAll((int) $u['id']);
        Audit::log('user.sessions_revoked', 'user', $u['id']);
        Auth::logout();
        redirect('/login?signed_out=1');
    }
}
