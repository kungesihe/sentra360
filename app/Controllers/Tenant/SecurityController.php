<?php
declare(strict_types=1);

namespace App\Controllers\Tenant;

use App\Controllers\Controller;
use App\Core\Audit;
use App\Core\Auth;
use App\Core\DB;
use App\Core\TenantContext;

/**
 * Security company portal (/security): control room and response officers.
 * Read-only access to homeowners for now; incidents, panic alerts and
 * assignments arrive in Phases 2, 4 and 5.
 */
final class SecurityController extends Controller
{
    private function render(string $view, array $data): void
    {
        $this->view('security/' . $view, $data + ['me' => Auth::user(), 'portal' => 'security'], 'portal');
    }

    public function homeowners(): void
    {
        $this->render('homeowners', ['title' => 'Homeowners'] + AdminController::homeownerList());
    }

    public function homeowner(string $id): void
    {
        $m = AdminController::homeownerRecord($id);
        $this->render('homeowner', ['title' => $m['first_name'] . ' ' . $m['surname'], 'm' => $m]);
    }

    public function profile(): void
    {
        $this->render('profile', ['title' => 'My login', 'action' => '/security/password']);
    }

    public function passwordSave(): void
    {
        self::changePassword($this, '/security/profile');
    }

    /** Change the signed-in staff member's password; used by both staff portals. */
    public static function changePassword(Controller $c, string $back): never
    {
        $u = Auth::user();
        $fail = fn(array $e) => $c->backWithErrors($back, $e);
        if (!password_verify((string) ($_POST['current_password'] ?? ''), $u['password_hash'])) {
            $fail(['current_password' => 'Your current password is incorrect.']);
        }
        $pw = (string) ($_POST['password'] ?? '');
        if ($err = Auth::validatePassword($pw)) {
            $fail(['password' => $err]);
        }
        if ($pw !== ($_POST['password_confirm'] ?? '')) {
            $fail(['password_confirm' => 'Passwords do not match.']);
        }
        DB::update('users', ['password_hash' => password_hash($pw, PASSWORD_DEFAULT)], ['id' => $u['id'], 'tenant_id' => TenantContext::id()]);
        DB::run('UPDATE user_sessions SET revoked_at = NOW() WHERE user_id = ? AND token_hash <> ? AND revoked_at IS NULL',
            [$u['id'], hash('sha256', $_SESSION['auth']['token'])]);
        Audit::log('user.password_changed', 'user', $u['id']);
        flash('success', 'Password changed. Your other devices were signed out.');
        redirect($back);
    }
}
