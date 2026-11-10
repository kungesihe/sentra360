<?php
/**
 * All routes. One company, three sign-ins:
 *   Homeowner          /login           → /account
 *   Admin              /admin/login     → /admin
 *   Security company   /security/login  → /security
 */

use App\Controllers\DevController;
use App\Controllers\Tenant\AccountController;
use App\Controllers\Tenant\AdminController;
use App\Controllers\Tenant\AdminOpsController;
use App\Controllers\Tenant\AuthController;
use App\Controllers\Tenant\FilesController;
use App\Controllers\Tenant\HomeAssetsController;
use App\Controllers\Tenant\HomeCommerceController;
use App\Controllers\Tenant\HomeIncidentsController;
use App\Controllers\Tenant\PublicController;
use App\Controllers\Tenant\SecurityController;
use App\Controllers\Tenant\StaffIncidentsController;
use App\Core\Auth;

/** @var App\Core\Router $router */

// ---------------------------------------------------------------- public
$router->get('/', [PublicController::class, 'landing']);
$router->get('/privacy', [PublicController::class, 'privacy']);
$router->get('/terms', [PublicController::class, 'terms']);
$router->get('/logo', [PublicController::class, 'logo']);
$router->get('/services', [HomeCommerceController::class, 'services']);
$router->get('/services/{id}', [HomeCommerceController::class, 'serviceShow']);
$router->get('/files/{id}', [FilesController::class, 'show']);
$router->postExternal('/payments/payfast/notify', [HomeCommerceController::class, 'payfastNotify']);

// Sign-in pages (one per login type)
$router->get('/login', [AuthController::class, 'loginForm']);
$router->post('/login', [AuthController::class, 'login']);
$router->get('/admin/login', [AuthController::class, 'loginForm']);
$router->post('/admin/login', [AuthController::class, 'login']);
$router->get('/security/login', [AuthController::class, 'loginForm']);
$router->post('/security/login', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout']);

// Homeowner registration and password recovery
$router->get('/register', [AuthController::class, 'registerForm']);
$router->post('/register', [AuthController::class, 'register']);
$router->get('/verify-email', [AuthController::class, 'verifyEmail']);
$router->get('/forgot-password', [AuthController::class, 'forgotForm']);
$router->post('/forgot-password', [AuthController::class, 'forgot']);
$router->get('/reset-password', [AuthController::class, 'resetForm']);
$router->post('/reset-password', [AuthController::class, 'reset']);

// ------------------------------------------------------------- homeowner
$home = [Auth::requirePortal('homeowner')];
$router->get('/account', [AccountController::class, 'dashboard'], $home);
$router->get('/account/verify', [AccountController::class, 'verifyForm'], $home);
$router->post('/account/verify/mobile', [AccountController::class, 'verifyMobile'], $home);
$router->post('/account/verify/resend-sms', [AccountController::class, 'resendSms'], $home);
$router->post('/account/verify/resend-email', [AccountController::class, 'resendEmail'], $home);
$router->get('/account/profile', [AccountController::class, 'profile'], $home);
$router->post('/account/profile', [AccountController::class, 'profileSave'], $home);
$router->post('/account/password', [AccountController::class, 'passwordSave'], $home);
$router->post('/account/sessions/revoke', [AccountController::class, 'revokeSessions'], $home);

$router->get('/account/incidents', [HomeIncidentsController::class, 'index'], $home);
$router->get('/account/incidents/new', [HomeIncidentsController::class, 'create'], $home);
$router->post('/account/incidents', [HomeIncidentsController::class, 'store'], $home);
$router->get('/account/incidents/{id}', [HomeIncidentsController::class, 'show'], $home);
$router->post('/account/incidents/{id}/info', [HomeIncidentsController::class, 'addInfo'], $home);

$router->get('/account/panic', [HomeIncidentsController::class, 'panic'], $home);
$router->post('/account/panic', [HomeIncidentsController::class, 'trigger'], $home);
$router->get('/account/panic/{ref}', [HomeIncidentsController::class, 'panicStatus'], $home);
$router->get('/account/panic/{ref}/status', [HomeIncidentsController::class, 'panicJson'], $home);
$router->post('/account/panic/{ref}/location', [HomeIncidentsController::class, 'panicLocation'], $home);
$router->post('/account/panic/{ref}/cancel', [HomeIncidentsController::class, 'panicCancel'], $home);

$router->get('/account/contacts', [HomeIncidentsController::class, 'contacts'], $home);
$router->post('/account/contacts', [HomeIncidentsController::class, 'contactAdd'], $home);
$router->post('/account/contacts/{id}/delete', [HomeIncidentsController::class, 'contactDelete'], $home);

$router->get('/account/assets/{kind}', [HomeAssetsController::class, 'index'], $home);
$router->get('/account/assets/{kind}/new', [HomeAssetsController::class, 'create'], $home);
$router->post('/account/assets/{kind}', [HomeAssetsController::class, 'store'], $home);
$router->post('/account/assets/{kind}/{id}/apply', [HomeAssetsController::class, 'apply'], $home);
$router->get('/account/applications', [HomeAssetsController::class, 'applications'], $home);
$router->get('/account/applications/{kind}/{id}', [HomeAssetsController::class, 'application'], $home);
$router->post('/account/applications/{kind}/{id}/note', [HomeAssetsController::class, 'applicationNote'], $home);
$router->post('/account/applications/{kind}/{id}/cancel', [HomeAssetsController::class, 'applicationCancel'], $home);

$router->get('/account/services', [HomeCommerceController::class, 'services'], $home);
$router->get('/account/services/{id}', [HomeCommerceController::class, 'serviceShow'], $home);
$router->post('/account/services/{id}/request', [HomeCommerceController::class, 'requestStore'], $home);
$router->get('/account/requests', [HomeCommerceController::class, 'requests'], $home);
$router->get('/account/requests/{id}', [HomeCommerceController::class, 'requestShow'], $home);
$router->post('/account/requests/{id}/cancel', [HomeCommerceController::class, 'requestCancel'], $home);
$router->get('/account/quotations', [HomeCommerceController::class, 'quotations'], $home);
$router->get('/account/quotations/{id}', [HomeCommerceController::class, 'quotation'], $home);
$router->post('/account/quotations/{id}/respond', [HomeCommerceController::class, 'quotationRespond'], $home);
$router->post('/account/quotations/{id}/pay', [HomeCommerceController::class, 'payOnline'], $home);
$router->get('/account/payments', [HomeCommerceController::class, 'payments'], $home);
$router->get('/account/notifications', [HomeCommerceController::class, 'notifications'], $home);

// ---------------------------------------------------------- admin portal
$admin = [Auth::requirePortal('admin')];
$router->get('/admin', [AdminController::class, 'dashboard'], $admin);
$router->get('/admin/branding', [AdminController::class, 'branding'], $admin);
$router->post('/admin/branding', [AdminController::class, 'brandingSave'], $admin);
$router->get('/admin/homeowners', [AdminController::class, 'members'], $admin);
$router->get('/admin/homeowners/{id}', [AdminController::class, 'member'], $admin);
$router->post('/admin/homeowners/{id}/status', [AdminController::class, 'memberStatus'], $admin);
$router->get('/admin/staff', [AdminController::class, 'staff'], $admin);
$router->post('/admin/staff', [AdminController::class, 'staffCreate'], $admin);
$router->post('/admin/staff/{id}/status', [AdminController::class, 'staffStatus'], $admin);
$router->get('/admin/audit', [AdminController::class, 'audit'], $admin);
$router->get('/admin/profile', [AdminController::class, 'profile'], $admin);
$router->post('/admin/password', [AdminController::class, 'passwordSave'], $admin);
$router->get('/admin/notifications', [HomeCommerceController::class, 'notifications'], $admin);

$router->get('/admin/incidents', [StaffIncidentsController::class, 'index'], $admin);
$router->get('/admin/incidents/new', [StaffIncidentsController::class, 'create'], $admin);
$router->post('/admin/incidents', [StaffIncidentsController::class, 'store'], $admin);
$router->get('/admin/incidents/{id}', [StaffIncidentsController::class, 'show'], $admin);
$router->post('/admin/incidents/{id}/status', [StaffIncidentsController::class, 'status'], $admin);
$router->post('/admin/incidents/{id}/note', [StaffIncidentsController::class, 'note'], $admin);
$router->post('/admin/incidents/{id}/assign', [StaffIncidentsController::class, 'assign'], $admin);
$router->post('/admin/incidents/{id}/acknowledge', [StaffIncidentsController::class, 'acknowledge'], $admin);
$router->get('/admin/patrols', [StaffIncidentsController::class, 'patrols'], $admin);

$router->get('/admin/applications', [AdminOpsController::class, 'applications'], $admin);
$router->get('/admin/applications/{kind}/{id}', [AdminOpsController::class, 'application'], $admin);
$router->post('/admin/applications/{kind}/{id}/status', [AdminOpsController::class, 'applicationStatus'], $admin);
$router->get('/admin/quotations', [AdminOpsController::class, 'quotations'], $admin);
$router->get('/admin/quotations/new/{type}/{id}', [AdminOpsController::class, 'quoteForm'], $admin);
$router->post('/admin/quotations/new/{type}/{id}', [AdminOpsController::class, 'quoteStore'], $admin);
$router->get('/admin/quotations/{id}', [AdminOpsController::class, 'quotation'], $admin);
$router->post('/admin/quotations/{id}/action', [AdminOpsController::class, 'quotationAction'], $admin);
$router->post('/admin/quotations/{id}/payment', [AdminOpsController::class, 'paymentStore'], $admin);
$router->get('/admin/payments', [AdminOpsController::class, 'payments'], $admin);
$router->get('/admin/services', [AdminOpsController::class, 'services'], $admin);
$router->get('/admin/services/new', [AdminOpsController::class, 'serviceForm'], $admin);
$router->post('/admin/services/new', [AdminOpsController::class, 'serviceSave'], $admin);
$router->get('/admin/services/{id}', [AdminOpsController::class, 'serviceForm'], $admin);
$router->post('/admin/services/{id}', [AdminOpsController::class, 'serviceSave'], $admin);
$router->get('/admin/requests', [AdminOpsController::class, 'requests'], $admin);
$router->get('/admin/requests/{id}', [AdminOpsController::class, 'request'], $admin);
$router->post('/admin/requests/{id}/status', [AdminOpsController::class, 'requestStatus'], $admin);
$router->get('/admin/alerts', [AdminOpsController::class, 'alerts'], $admin);
$router->post('/admin/alerts', [AdminOpsController::class, 'alertStore'], $admin);
$router->post('/admin/alerts/{id}/end', [AdminOpsController::class, 'alertEnd'], $admin);
$router->get('/admin/reports', [AdminOpsController::class, 'reports'], $admin);
$router->get('/admin/reports/export/{type}', [AdminOpsController::class, 'export'], $admin);

// ------------------------------------------------------- security portal
$security = [Auth::requirePortal('security')];
$router->get('/security', [StaffIncidentsController::class, 'controlRoom'], $security);
$router->get('/security/feed', [StaffIncidentsController::class, 'feed'], $security);
$router->get('/security/assignments', [StaffIncidentsController::class, 'myAssignments'], $security);
$router->get('/security/incidents', [StaffIncidentsController::class, 'index'], $security);
$router->get('/security/incidents/new', [StaffIncidentsController::class, 'create'], $security);
$router->post('/security/incidents', [StaffIncidentsController::class, 'store'], $security);
$router->get('/security/incidents/{id}', [StaffIncidentsController::class, 'show'], $security);
$router->post('/security/incidents/{id}/status', [StaffIncidentsController::class, 'status'], $security);
$router->post('/security/incidents/{id}/note', [StaffIncidentsController::class, 'note'], $security);
$router->post('/security/incidents/{id}/assign', [StaffIncidentsController::class, 'assign'], $security);
$router->post('/security/incidents/{id}/acknowledge', [StaffIncidentsController::class, 'acknowledge'], $security);
$router->post('/security/incidents/{id}/response', [StaffIncidentsController::class, 'response'], $security);
$router->get('/security/patrols', [StaffIncidentsController::class, 'patrols'], $security);
$router->post('/security/patrols', [StaffIncidentsController::class, 'patrolStore'], $security);
$router->get('/security/homeowners', [SecurityController::class, 'homeowners'], $security);
$router->get('/security/homeowners/{id}', [SecurityController::class, 'homeowner'], $security);
$router->get('/security/profile', [SecurityController::class, 'profile'], $security);
$router->post('/security/password', [SecurityController::class, 'passwordSave'], $security);
$router->get('/security/notifications', [HomeCommerceController::class, 'notifications'], $security);

if (is_dev()) {
    $router->get('/dev/outbox', [DevController::class, 'outbox']);
}
