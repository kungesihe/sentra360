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

/**
 * Sign-in for the three account types, homeowner registration,
 * email verification and password recovery.
 */
final class AuthController extends Controller
{
    private function currentPortal(): string
    {
        return Auth::portalForPath(parse_url($_SERVER['REQUEST_URI'] ?? '/login', PHP_URL_PATH) ?: '/login');
    }

    public function registerForm(): void
    {
        if (Auth::user()) {
            redirect(Auth::PORTALS[Auth::user()['portal']][2]);
        }
        $this->view('tenant/register', ['title' => 'Create your homeowner account'], 'tenant');
    }

    public function register(): void
    {
        $tid = TenantContext::id();
        RateLimiter::guard('register:' . client_ip(), 10, 3600);
        $v = $this->validate([
            'first_name' => 'required,max:100', 'surname' => 'required,max:100',
            'mobile' => 'required,mobile', 'email' => 'required,email,max:190', 'password' => 'required',
        ]);
        $pw = (string) ($_POST['password'] ?? '');
        if ($err = Auth::validatePassword($pw)) {
            $v->addError('password', $err);
        } elseif ($pw !== ($_POST['password_confirm'] ?? '')) {
            $v->addError('password_confirm', 'Passwords do not match.');
        }
        if (empty($_POST['consent'])) {
            $v->addError('consent', 'Please accept the terms and privacy policy to continue.');
        }
        if (!$v->fails()) {
            if (UserService::emailTaken($tid, $v->clean['email'])) {
                $v->addError('email', 'An account with this email already exists. Try signing in or resetting your password.');
            }
            if (UserService::mobileTaken($tid, $v->clean['mobile'])) {
                $v->addError('mobile', 'An account with this mobile number already exists.');
            }
        }
        if ($v->fails()) {
            $this->backWithErrors('/register', $v->errors);
        }
        $roleId = (int) DB::value('SELECT id FROM roles WHERE code = "homeowner"');
        $userId = DB::transaction(function () use ($tid, $roleId, $v, $pw) {
            $id = UserService::create($tid, $roleId, array_merge($v->clean, ['password' => $pw]), 'pending');
            DB::insert('consents', [
                'tenant_id' => $tid, 'user_id' => $id, 'consent_type' => 'terms_privacy',
                'document_version' => config('policy_version'), 'granted' => 1, 'ip_address' => client_ip(),
            ]);
            if (!empty($_POST['marketing'])) {
                DB::insert('consents', [
                    'tenant_id' => $tid, 'user_id' => $id, 'consent_type' => 'marketing',
                    'document_version' => config('policy_version'), 'granted' => 1, 'ip_address' => client_ip(),
                ]);
            }
            return $id;
        });
        Audit::log('user.registered', 'user', $userId, [], $tid, 'guest');
        $user = UserService::find($tid, $userId);
        $t = TenantContext::current();
        $_SESSION['delivery'] = [
            'sms' => VerificationService::sendMobileOtp($user, $t),
            'email' => VerificationService::sendEmailVerification($user, $t),
        ];
        Auth::startSession($userId, $tid);
        redirect('/account/verify');
    }

    public function loginForm(): void
    {
        $portal = $this->currentPortal();
        if ($u = Auth::user()) {
            redirect(Auth::PORTALS[$u['portal']][2]);
        }
        $this->view('tenant/login', [
            'title' => Auth::PORTALS[$portal][3] . ' sign in',
            'portal' => $portal,
            'action' => Auth::PORTALS[$portal][1],
        ], 'tenant');
    }

    public function login(): void
    {
        $tid = TenantContext::id();
        $portal = $this->currentPortal();
        $back = Auth::PORTALS[$portal][1];
        $bucket = 'login:' . client_ip();
        if (RateLimiter::tooMany($bucket, 20, 900)) {
            $this->backWithErrors($back, ['identifier' => 'Too many sign-in attempts from your network. Please wait 15 minutes.']);
        }
        RateLimiter::hit($bucket);
        $res = Auth::attempt($portal, (string) ($_POST['identifier'] ?? ''), (string) ($_POST['password'] ?? ''));
        if (!$res['ok']) {
            Audit::log('auth.login_failed', 'user', null, ['portal' => $portal, 'identifier' => mb_substr((string) ($_POST['identifier'] ?? ''), 0, 60)], $tid, 'guest');
            $this->backWithErrors($back, ['identifier' => $res['error']]);
        }
        $u = $res['user'];
        Auth::startSession((int) $u['id'], $tid);
        Audit::log('auth.login', 'user', $u['id'], ['portal' => $portal], $tid, 'user', (int) $u['id']);
        $home = Auth::PORTALS[$portal][2];
        $intended = $_SESSION['_intended'] ?? null;
        unset($_SESSION['_intended']);
        if (is_string($intended) && str_starts_with($intended, $home) && !str_starts_with($intended, '//')) {
            redirect($intended);
        }
        redirect($home);
    }

    public function logout(): void
    {
        $login = '/login';
        if ($u = Auth::user()) {
            Audit::log('auth.logout', 'user', $u['id']);
            $login = Auth::PORTALS[$u['portal']][1] ?? '/login';
        }
        Auth::logout();
        redirect($login . '?signed_out=1');
    }

    public function verifyEmail(): void
    {
        $tid = TenantContext::id();
        $user = VerificationService::checkToken($tid, (string) ($_GET['u'] ?? ''), (string) ($_GET['t'] ?? ''), 'verify_email', true);
        if (!$user) {
            flash('danger', 'That verification link is invalid or has expired. Sign in to request a new one.');
            redirect(Auth::user() ? '/account/verify' : '/login');
        }
        DB::update('users', ['email_verified_at' => now()], ['id' => $user['id'], 'tenant_id' => $tid]);
        UserService::refreshStatus((int) $user['id']);
        Audit::log('user.email_verified', 'user', $user['id'], [], $tid, 'user', (int) $user['id']);
        flash('success', 'Your email address is confirmed.');
        redirect(Auth::user() ? '/account/verify' : '/login');
    }

    public function forgotForm(): void
    {
        $this->view('tenant/forgot', ['title' => 'Reset your password'], 'tenant');
    }

    public function forgot(): void
    {
        $tid = TenantContext::id();
        RateLimiter::guard('forgot:' . client_ip(), 5, 900);
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $user = DB::one('SELECT * FROM users WHERE tenant_id = ? AND email = ?', [$tid, $email]);
        if ($user && !in_array($user['status'], ['suspended', 'disabled'], true)) {
            VerificationService::sendPasswordReset($user, TenantContext::current());
            Audit::log('auth.password_reset_requested', 'user', $user['id'], [], $tid, 'guest');
        }
        // Same answer whether or not the account exists (prevents account discovery).
        $_SESSION['forgot_sent'] = true;
        redirect('/forgot-password');
    }

    public function resetForm(): void
    {
        $u = (string) ($_GET['u'] ?? '');
        $t = (string) ($_GET['t'] ?? '');
        $valid = VerificationService::checkToken(TenantContext::id(), $u, $t, 'password_reset', false) !== null;
        $this->view('tenant/reset', ['title' => 'Choose a new password', 'valid' => $valid, 'u' => $u, 't' => $t], 'tenant');
    }

    public function reset(): void
    {
        $tid = TenantContext::id();
        $u = (string) ($_POST['u'] ?? '');
        $t = (string) ($_POST['t'] ?? '');
        $back = '/reset-password?u=' . urlencode($u) . '&t=' . urlencode($t);
        $pw = (string) ($_POST['password'] ?? '');
        if ($err = Auth::validatePassword($pw)) {
            $this->backWithErrors($back, ['password' => $err]);
        }
        if ($pw !== ($_POST['password_confirm'] ?? '')) {
            $this->backWithErrors($back, ['password_confirm' => 'Passwords do not match.']);
        }
        $user = VerificationService::checkToken($tid, $u, $t, 'password_reset', true);
        if (!$user) {
            flash('danger', 'That reset link is invalid or has expired. Please request a new one.');
            redirect('/forgot-password');
        }
        DB::update('users', [
            'password_hash' => password_hash($pw, PASSWORD_DEFAULT),
            'failed_login_count' => 0, 'locked_until' => null,
        ], ['id' => $user['id'], 'tenant_id' => $tid]);
        Auth::revokeAll((int) $user['id']);
        Audit::log('auth.password_reset', 'user', $user['id'], [], $tid, 'user', (int) $user['id']);
        $role = (string) DB::value('SELECT code FROM roles WHERE id = ?', [$user['role_id']]);
        flash('success', 'Your password has been changed and all other devices were signed out. Please sign in.');
        redirect(Auth::PORTALS[Auth::portalForRole($role) ?? 'homeowner'][1]);
    }
}
