<?php
declare(strict_types=1);

use App\Core\DB;
use App\Services\DemoSeeder;
use Tests\Http;
use Tests\T;

T::test('Homeowner registration requires a CSRF token', function () {
    $c = new Http(T::HOST);
    $c->get('/register');
    $c->post('/register', ['first_name' => 'A', 'surname' => 'B', 'mobile' => '0825550000', 'email' => 'x@example.test', 'password' => 'Password123', 'password_confirm' => 'Password123', 'consent' => 1], 'not-the-token');
    T::eq(419, $c->status, 'POST with a bad CSRF token is rejected');
    T::eq(0, (int) DB::value('SELECT COUNT(*) FROM users WHERE email = ?', ['x@example.test']), 'no user created');
});

T::test('Weak passwords and missing consent are rejected', function () {
    $c = new Http(T::HOST);
    $c->get('/register');
    $c->post('/register', ['first_name' => 'A', 'surname' => 'B', 'mobile' => '0825550001', 'email' => 'weak@example.test', 'password' => 'short', 'password_confirm' => 'short']);
    T::redirectsTo('/register', $c, 'redirects back to the form');
    $c->get('/register');
    T::contains('at least 10 characters', $c->body, 'shows password rule');
    T::contains('accept the terms', $c->body, 'shows consent error');
});

T::test('Homeowner registration → mobile OTP → email link → active account (no fake delivery)', function () {
    $c = new Http(T::HOST);
    $c->get('/register');
    $c->post('/register', ['first_name' => 'Lindiwe', 'surname' => 'M', 'mobile' => '082 555 0002', 'email' => 'Lindiwe@Example.test', 'password' => 'Password123', 'password_confirm' => 'Password123', 'consent' => 1]);
    T::redirectsTo('/account/verify', $c, 'goes to verification');
    $u = DB::one('SELECT u.*, r.code AS role_code FROM users u JOIN roles r ON r.id = u.role_id WHERE email = ?', ['lindiwe@example.test']);
    T::true($u !== null, 'user stored with lower-cased email');
    T::eq('homeowner', $u['role_code'], 'self-registered accounts are homeowners');
    T::eq('+27825550002', $u['mobile'], 'mobile normalised to E.164');
    T::eq('pending', $u['status'], 'new account is pending');
    T::true(password_verify('Password123', $u['password_hash']), 'password stored as a hash');
    T::eq(1, (int) DB::value('SELECT COUNT(*) FROM consents WHERE user_id = ? AND consent_type = "terms_privacy" AND granted = 1', [$u['id']]), 'POPIA consent recorded');

    T::eq('not_configured', DB::value('SELECT status FROM notifications WHERE user_id = ? AND channel = "sms" ORDER BY id DESC LIMIT 1', [$u['id']]), 'SMS recorded as not delivered');
    $c->get('/account/verify');
    T::contains('not</u> being sent', $c->body, 'page tells the user SMS is not being sent');
    T::notContains('We sent a 6-digit code', $c->body, 'page does not claim an SMS was sent');

    preg_match('/code is (\d{6})/', T::lastMessage('+27825550002'), $m);
    $c->post('/account/verify/mobile', ['code' => $m[1] === '000000' ? '111111' : '000000']);
    $c->get('/account/verify');
    T::contains('incorrect', $c->body, 'wrong OTP rejected');
    $c->post('/account/verify/mobile', ['code' => $m[1]]);
    T::true(DB::value('SELECT mobile_verified_at FROM users WHERE id = ?', [$u['id']]) !== null, 'mobile verified with correct OTP');

    preg_match('#(/verify-email\?[^\s]+)#', T::lastMessage('lindiwe@example.test'), $m);
    $c->get($m[1]);
    T::eq('active', DB::value('SELECT status FROM users WHERE id = ?', [$u['id']]), 'account active after both verifications');
    $c->get($m[1]);
    $c->get('/account');
    T::contains('invalid or has expired', $c->body, 'email link is single-use');
});

T::test('Account locks after 5 failed sign-ins', function () {
    $u = T::user('homeowner');
    $c = new Http(T::HOST);
    for ($i = 0; $i < 5; $i++) {
        $c->get('/login');
        $c->post('/login', ['identifier' => $u['email'], 'password' => 'wrong-password-1']);
    }
    T::true(DB::value('SELECT locked_until FROM users WHERE id = ?', [$u['id']]) !== null, 'locked_until set');
    $c->get('/login');
    $c->post('/login', ['identifier' => $u['email'], 'password' => DemoSeeder::PASSWORD]);
    $c->get('/login');
    T::contains('locked', $c->body, 'correct password refused while locked');
    T::eq(1, (int) DB::value('SELECT COUNT(*) FROM audit_logs WHERE action = "auth.locked" AND entity_id = ?', [(string) $u['id']]), 'lockout audited');
});

T::test('Sign-in by mobile number works and sign-out ends the session', function () {
    $u = T::user('homeowner');
    $c = new Http(T::HOST);
    $c->get('/login');
    $c->post('/login', ['identifier' => '0' . substr($u['mobile'], 3), 'password' => DemoSeeder::PASSWORD]);
    T::redirectsTo('/account', $c, 'mobile number sign-in');
    $c->get('/account');
    T::eq(200, $c->status, 'account page available');
    $stolen = $c->cookie('SENTRASESS');
    $c->post('/logout');
    T::redirectsTo('/login', $c, 'homeowner returns to homeowner login');
    $thief = new Http(T::HOST);
    $thief->request('GET', '/account', [], ['Cookie: SENTRASESS=' . $stolen]);
    T::redirectsTo('/login', $thief, 'old session cookie no longer works');
});

T::test('Password reset: generic response, single-use token, other sessions revoked', function () {
    $u = T::user('homeowner');
    $other = T::login($u);

    $c = new Http(T::HOST);
    $c->get('/forgot-password');
    $c->post('/forgot-password', ['email' => 'nobody@example.test']);
    $c->get('/forgot-password');
    $unknown = $c->body;
    $c->post('/forgot-password', ['email' => $u['email']]);
    $c->get('/forgot-password');
    T::contains('If an account exists', $unknown, 'same message for unknown email');
    T::contains('If an account exists', $c->body, 'same message for known email');

    preg_match('#(/reset-password\?[^\s]+)#', T::lastMessage($u['email']), $m);
    $c->get($m[1]);
    T::contains('Choose a new password', $c->body, 'reset form shown');
    parse_str((string) parse_url($m[1], PHP_URL_QUERY), $q);
    $c->post('/reset-password', ['u' => $q['u'], 't' => $q['t'], 'password' => 'BrandNew1234', 'password_confirm' => 'BrandNew1234']);
    T::redirectsTo('/login', $c, 'reset succeeds');
    $c->post('/reset-password', ['u' => $q['u'], 't' => $q['t'], 'password' => 'Another12345', 'password_confirm' => 'Another12345']);
    T::redirectsTo('/forgot-password', $c, 'token cannot be reused');

    $other->get('/account');
    T::redirectsTo('/login', $other, 'previously signed-in device was signed out');
    T::login($u, 'BrandNew1234');
});

T::test('Sessions are stored in the database and survive a server restart', function () {
    $u = T::user('homeowner');
    $c = T::login($u);
    $sid = $c->cookie('SENTRASESS');
    T::true((bool) DB::value('SELECT 1 FROM php_sessions WHERE id = ?', [$sid]), 'session row exists');
    // A made-up session id is not accepted (strict mode).
    $fake = new Http(T::HOST);
    $fake->request('GET', '/account', [], ['Cookie: SENTRASESS=madeupsessionid1234567890abcdef']);
    T::redirectsTo('/login', $fake, 'unknown session id rejected');
    T::eq(null, DB::value('SELECT id FROM php_sessions WHERE id = ?', ['madeupsessionid1234567890abcdef']), 'fake id not adopted');
});

T::test('Forwarded IP headers are ignored unless TRUST_PROXY is on', function () {
    // The test server runs without TRUST_PROXY, so a spoofed header must not dodge the login rate limit.
    $u = T::user('homeowner');
    for ($i = 0; $i < 21; $i++) {
        $c = new Http(T::HOST);
        $c->get('/login');
        $c->request('POST', '/login', ['_csrf' => $c->csrf(), 'identifier' => 'nobody' . $i . '@example.test', 'password' => 'x'], ['X-Forwarded-For: 10.0.0.' . $i]);
    }
    $c->get('/login');
    T::contains('Too many sign-in attempts', $c->body, 'spoofed IPs share one limit');
});

T::test('A security officer resetting their password is sent back to the security login', function () {
    $s = T::user('security_staff');
    $c = new Http(T::HOST);
    $c->get('/forgot-password');
    $c->post('/forgot-password', ['email' => $s['email']]);
    preg_match('#(/reset-password\?[^\s]+)#', T::lastMessage($s['email']), $m);
    parse_str((string) parse_url($m[1], PHP_URL_QUERY), $q);
    $c->get($m[1]);
    $c->post('/reset-password', ['u' => $q['u'], 't' => $q['t'], 'password' => 'Officer12345', 'password_confirm' => 'Officer12345']);
    T::redirectsTo('/security/login', $c, 'redirect to the right login page');
});
