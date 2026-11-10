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
use App\Controllers\Tenant\AuthController;
use App\Controllers\Tenant\PublicController;
use App\Controllers\Tenant\SecurityController;
use App\Core\Auth;

/** @var App\Core\Router $router */

// Public site
$router->get('/', [PublicController::class, 'landing']);
$router->get('/privacy', [PublicController::class, 'privacy']);
$router->get('/terms', [PublicController::class, 'terms']);
$router->get('/logo', [PublicController::class, 'logo']);

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

// Homeowner area
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

// Admin portal (the company's office)
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

// Security company portal (control room and response officers)
$security = [Auth::requirePortal('security')];
$router->get('/security', [SecurityController::class, 'dashboard'], $security);
$router->get('/security/homeowners', [SecurityController::class, 'homeowners'], $security);
$router->get('/security/homeowners/{id}', [SecurityController::class, 'homeowner'], $security);
$router->get('/security/profile', [SecurityController::class, 'profile'], $security);
$router->post('/security/password', [SecurityController::class, 'passwordSave'], $security);

if (is_dev()) {
    $router->get('/dev/outbox', [DevController::class, 'outbox']);
}
