<?php
declare(strict_types=1);

use App\Core\DB;
use App\Services\DemoSeeder;
use Tests\Http;
use Tests\T;

T::test('Each portal sends signed-out visitors to its own login page', function () {
    $c = new Http(T::HOST);
    foreach (['/account' => '/login', '/admin' => '/admin/login', '/admin/homeowners' => '/admin/login', '/security' => '/security/login', '/security/homeowners' => '/security/login'] as $page => $login) {
        $c->get($page);
        T::redirectsTo($login, $c, "$page requires sign-in");
    }
});

T::test('Each account type lands on its own portal', function () {
    foreach (['homeowner' => '/account', 'admin' => '/admin', 'security_staff' => '/security'] as $role => $home) {
        $u = T::user($role);
        $c = new Http(T::HOST);
        $c->get(T::LOGIN[$role]);
        $c->post(T::LOGIN[$role], ['identifier' => $u['email'], 'password' => DemoSeeder::PASSWORD]);
        T::redirectsTo($home, $c, "$role lands on $home");
        $c->get($home);
        T::eq(200, $c->status, "$role can open $home");
    }
});

T::test('Accounts cannot sign in through another login page', function () {
    $cases = [
        ['homeowner', '/admin/login'], ['homeowner', '/security/login'],
        ['security_staff', '/admin/login'], ['security_staff', '/login'],
        ['admin', '/security/login'], ['admin', '/login'],
    ];
    foreach ($cases as [$role, $wrongLogin]) {
        $u = T::user($role);
        $c = new Http(T::HOST);
        $c->get($wrongLogin);
        $c->post($wrongLogin, ['identifier' => $u['email'], 'password' => DemoSeeder::PASSWORD]);
        T::redirectsTo($wrongLogin, $c, "$role refused at $wrongLogin");
        $c->get($wrongLogin);
        T::contains('Please sign in on the', $c->body, 'explains which login to use');
        T::eq(null, $c->cookie('SENTRASESS') ? DB::value('SELECT id FROM user_sessions WHERE user_id = ? LIMIT 1', [$u['id']]) : null, "no session created for $role at $wrongLogin");
    }
});

T::test('Homeowners cannot open the admin or security portals', function () {
    $c = T::login(T::user('homeowner'));
    foreach (['/admin', '/admin/homeowners', '/admin/staff', '/security', '/security/homeowners'] as $p) {
        $c->get($p);
        T::eq(403, $c->status, "homeowner gets 403 on $p");
    }
    T::true((int) DB::value('SELECT COUNT(*) FROM audit_logs WHERE action = "access.denied"') >= 5, 'denials are audited');
});

T::test('Security staff can look up homeowners but cannot use the admin portal', function () {
    $h = T::user('homeowner');
    $c = T::login(T::user('security_staff'));
    $c->get('/security/homeowners');
    T::eq(200, $c->status, 'list homeowners');
    $c->get('/security/homeowners/' . $h['id']);
    T::eq(200, $c->status, 'open a homeowner');
    T::contains($h['mobile'], $c->body, 'full mobile shown on the record');
    T::eq(1, (int) DB::value('SELECT COUNT(*) FROM audit_logs WHERE action = "homeowner.viewed" AND entity_id = ?', [(string) $h['id']]), 'access logged');
    foreach (['/admin', '/admin/branding', '/admin/staff', '/admin/homeowners'] as $p) {
        $c->get($p);
        T::eq(403, $c->status, "security gets 403 on $p");
    }
    $c->post('/admin/homeowners/' . $h['id'] . '/status', ['action' => 'suspend']);
    T::eq(403, $c->status, 'security cannot suspend homeowners');
    T::eq('active', DB::value('SELECT status FROM users WHERE id = ?', [$h['id']]), 'homeowner unchanged');
});

T::test('Staff records are not reachable as homeowner records', function () {
    $other = T::user('security_staff');
    $c = T::login(T::user('admin'));
    $c->get('/admin/homeowners/' . $other['id']);
    T::eq(404, $c->status, 'staff account is not shown as a homeowner');
    $c->get('/security');
    T::eq(403, $c->status, 'admin is not a security login');
});

T::test('Admin creates a security login, which then works on the security login page only', function () {
    $c = T::login(T::user('admin'));
    $c->get('/admin/staff');
    $c->post('/admin/staff', ['role' => 'security_staff', 'first_name' => 'New', 'surname' => 'Officer', 'email' => 'new.officer@example.test', 'mobile' => '0825557777', 'password' => 'Officer12345']);
    T::redirectsTo('/admin/staff', $c, 'login created');
    $s = DB::one('SELECT u.*, r.code AS role_code FROM users u JOIN roles r ON r.id = u.role_id WHERE email = ?', ['new.officer@example.test']);
    T::eq('security_staff', $s['role_code'], 'stored as security staff');
    T::login($s, 'Officer12345');
});

T::test('Admin cannot create a homeowner-type or unknown login from the staff page', function () {
    $c = T::login(T::user('admin'));
    $c->get('/admin/staff');
    $c->post('/admin/staff', ['role' => 'homeowner', 'first_name' => 'X', 'surname' => 'Y', 'email' => 'sneaky@example.test', 'mobile' => '0825558888', 'password' => 'Password123']);
    T::eq(0, (int) DB::value('SELECT COUNT(*) FROM users WHERE email = "sneaky@example.test"'), 'rejected');
});

T::test('Disabling a security login signs it out and blocks sign-in; admins cannot disable themselves', function () {
    $admin = T::user('admin');
    $sec = T::user('security_staff');
    $secClient = T::login($sec);
    $c = T::login($admin);
    $c->get('/admin/staff');
    $c->post('/admin/staff/' . $sec['id'] . '/status', ['action' => 'disable']);
    $secClient->get('/security');
    T::redirectsTo('/security/login', $secClient, 'disabled officer signed out');
    $again = new Http(T::HOST);
    $again->get('/security/login');
    $again->post('/security/login', ['identifier' => $sec['email'], 'password' => DemoSeeder::PASSWORD]);
    $again->get('/security/login');
    T::contains('not active', $again->body, 'disabled login refused');

    $c->get('/admin/staff');
    $c->post('/admin/staff/' . $admin['id'] . '/status', ['action' => 'disable']);
    T::eq('active', DB::value('SELECT status FROM users WHERE id = ?', [$admin['id']]), 'admin still active');
});

T::test('Admin can verify, suspend and mark homeowners as clients', function () {
    $h = T::user('homeowner', 'pending');
    $c = T::login(T::user('admin'));
    $c->get('/admin/homeowners/' . $h['id']);
    $c->post('/admin/homeowners/' . $h['id'] . '/status', ['action' => 'verify']);
    T::eq('active', DB::value('SELECT status FROM users WHERE id = ?', [$h['id']]), 'verified');
    $c->post('/admin/homeowners/' . $h['id'] . '/status', ['action' => 'make_client']);
    T::eq('client', DB::value('SELECT client_status FROM users WHERE id = ?', [$h['id']]), 'marked as client');
    $hc = T::login($h);
    $c->post('/admin/homeowners/' . $h['id'] . '/status', ['action' => 'suspend']);
    $hc->get('/account');
    T::redirectsTo('/login', $hc, 'suspended homeowner signed out');
});

T::test('The removed multi-company pages no longer exist', function () {
    $c = new Http(T::HOST);
    foreach (['/owner', '/owner/login', '/register-company', '/pricing', '/demo', '/features'] as $p) {
        $c->get($p);
        T::eq(404, $c->status, "$p is gone");
    }
    $c->get('/');
    T::eq(200, $c->status, 'home page is the company site');
    T::contains('Test Security Co', $c->body, 'shows the company');
});
