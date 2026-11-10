<?php
declare(strict_types=1);

use App\Core\DB;
use Tests\Http;
use Tests\T;

/** 1×1 PNG for upload tests. */
function tiny_png(): string
{
    $f = tempnam(sys_get_temp_dir(), 'png');
    file_put_contents($f, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));
    return $f;
}

function category_id(string $code): int
{
    return (int) DB::value('SELECT id FROM incident_categories WHERE code = ?', [$code]);
}

T::test('Incident: report with photo → received → dispatched → in progress → resolved (reporter sees honest progress)', function () {
    $home = T::user('homeowner');
    $officer = T::user('security_staff');
    $h = T::login($home);
    $h->get('/account/incidents/new');
    $png = tiny_png();
    $h->post('/account/incidents', [
        'category_id' => category_id('house_break_in'), 'occurred_date' => date('Y-m-d'), 'occurred_time' => '01:30',
        'location_text' => '5 Oak Ave, Rivonia', 'latitude' => '-26.05', 'longitude' => '28.06',
        'description' => 'Back door forced open.', 'files[0]' => new CURLFile($png, 'image/png', 'door.png'),
    ]);
    unlink($png);
    $inc = DB::one('SELECT * FROM incidents WHERE reporter_id = ? ORDER BY id DESC LIMIT 1', [$home['id']]);
    T::true($inc !== null, 'incident stored');
    T::true((bool) preg_match('/^INC-\d{6}-[A-Z2-9]{4}$/', $inc['reference']), 'reference number format');
    T::eq('submitted', $inc['status'], 'starts as submitted, not dispatched');
    T::eq('high', $inc['priority'], 'priority from category');
    T::eq(1, (int) DB::value('SELECT COUNT(*) FROM files WHERE owner_type = "incident" AND owner_id = ?', [$inc['id']]), 'photo stored');

    $sec = T::login($officer);
    $sec->get('/security/incidents/' . $inc['id']);
    T::eq(200, $sec->status, 'security opens incident');
    T::eq('received', DB::value('SELECT status FROM incidents WHERE id = ?', [$inc['id']]), 'opening marks it received');
    $sec->post("/security/incidents/{$inc['id']}/note", ['note' => 'Neighbour CCTV may help', 'internal' => 1]);
    $sec->post("/security/incidents/{$inc['id']}/assign", ['officer_id' => $officer['id']]);
    T::eq('assigned', DB::value('SELECT status FROM incidents WHERE id = ?', [$inc['id']]), 'assigned = dispatched');
    $sec->post("/security/incidents/{$inc['id']}/response", ['status' => 'en_route']);
    T::eq('in_progress', DB::value('SELECT status FROM incidents WHERE id = ?', [$inc['id']]), 'en route → response in progress');
    $sec->post("/security/incidents/{$inc['id']}/response", ['status' => 'on_scene']);
    T::true(DB::value('SELECT arrived_at FROM incident_assignments WHERE incident_id = ?', [$inc['id']]) !== null, 'arrival time recorded');
    $sec->post("/security/incidents/{$inc['id']}/status", ['status' => 'resolved', 'resolution' => '']);
    T::eq('in_progress', DB::value('SELECT status FROM incidents WHERE id = ?', [$inc['id']]), 'cannot resolve without an outcome');
    $sec->post("/security/incidents/{$inc['id']}/status", ['status' => 'resolved', 'resolution' => 'Premises secured, SAPS case opened.']);
    T::eq('resolved', DB::value('SELECT status FROM incidents WHERE id = ?', [$inc['id']]), 'resolved');

    $h->get('/account/incidents/' . $inc['id']);
    T::contains('Premises secured', $h->body, 'reporter sees the outcome');
    T::notContains('Neighbour CCTV may help', $h->body, 'internal notes hidden from reporter');
    T::contains('has arrived', $h->body, 'reporter sees arrival update');
});

T::test('Incident privacy: other homeowners cannot see a report or its photos', function () {
    $a = T::user('homeowner');
    $b = T::user('homeowner');
    $ca = T::login($a);
    $ca->get('/account/incidents/new');
    $png = tiny_png();
    $ca->post('/account/incidents', ['category_id' => category_id('theft'), 'occurred_date' => date('Y-m-d'), 'occurred_time' => '10:00',
        'location_text' => 'Mall parking', 'description' => 'Bag stolen', 'files[0]' => new CURLFile($png, 'image/png', 'bag.png')]);
    unlink($png);
    $inc = DB::one('SELECT id FROM incidents WHERE reporter_id = ? ORDER BY id DESC LIMIT 1', [$a['id']]);
    $file = (int) DB::value('SELECT id FROM files WHERE owner_type = "incident" AND owner_id = ?', [$inc['id']]);
    $cb = T::login($b);
    $cb->get('/account/incidents/' . $inc['id']);
    T::eq(404, $cb->status, 'other homeowner gets 404 for the report');
    $cb->get('/files/' . $file);
    T::eq(403, $cb->status, 'other homeowner cannot open the photo');
    $ca->get('/files/' . $file);
    T::eq(200, $ca->status, 'reporter can open their photo');
    T::eq('image/png', $ca->headers['content-type'] ?? '', 'served as image');
    $anon = new Http(T::HOST);
    $anon->get('/files/' . $file);
    T::eq(404, $anon->status, 'signed-out visitor gets nothing');
});

T::test('Residential protection: application → quotation → acceptance → payment → activation → client with panic button', function () {
    $home = T::user('homeowner');
    $admin = T::user('admin');
    $h = T::login($home);
    $h->get('/account/panic');
    T::contains('Complete your profile to unlock the panic button', $h->body, 'panic locked until the profile is complete');
    T::notContains('data-panic-hold', $h->body, 'no panic button shown');
    $h->get('/account/assets/property/new');
    $h->post('/account/assets/property', ['name' => 'Home', 'property_type' => 'House', 'address' => '5 Oak Ave', 'suburb' => 'Rivonia', 'city' => 'Johannesburg',
        'apply' => 1, 'services' => ['Armed response', 'Alarm monitoring'], 'notes' => 'Two dogs']);
    $app = DB::one('SELECT * FROM property_applications WHERE applicant_id = ?', [$home['id']]);
    T::true($app !== null, 'application created');
    T::eq('submitted', $app['status'], 'submitted');
    T::eq('none', DB::value('SELECT protection_status FROM registered_properties WHERE id = ?', [$app['property_id']]), 'property not protected yet');

    $ad = T::login($admin);
    $ad->get("/admin/applications/property/{$app['id']}");
    $ad->post("/admin/applications/property/{$app['id']}/status", ['action' => 'assessment', 'note' => 'We will visit on Friday']);
    T::eq('assessment_required', DB::value('SELECT status FROM property_applications WHERE id = ?', [$app['id']]), 'assessment required');
    $ad->get("/admin/quotations/new/property/{$app['id']}");
    $ad->post("/admin/quotations/new/property/{$app['id']}", ['desc' => ['Armed response', 'Alarm monitoring', ''], 'qty' => ['1', '1', ''], 'price' => ['450', '200.50', ''],
        'billing' => 'monthly', 'valid_until' => date('Y-m-d', strtotime('+14 days')), 'vat' => 1, 'send' => '1']);
    $q = DB::one('SELECT * FROM quotations WHERE subject_type = "property" AND subject_id = ?', [$app['id']]);
    T::eq('sent', $q['status'], 'quotation sent');
    T::eq(65050, (int) $q['subtotal_cents'], 'subtotal');
    T::eq(9758, (int) $q['vat_cents'], 'VAT 15%');
    T::eq(74808, (int) $q['total_cents'], 'total');
    T::eq('awaiting_customer_acceptance', DB::value('SELECT status FROM property_applications WHERE id = ?', [$app['id']]), 'waiting for customer');

    $other = T::login(T::user('homeowner'));
    $other->get("/account/quotations/{$q['id']}");
    T::eq(404, $other->status, 'other homeowner cannot see the quotation');

    $h->get("/account/quotations/{$q['id']}");
    T::contains('R748.08', $h->body, 'customer sees total');
    $h->post("/account/quotations/{$q['id']}/respond", ['decision' => 'accept']);
    T::eq('accepted', DB::value('SELECT status FROM quotations WHERE id = ?', [$q['id']]), 'accepted');
    T::eq('awaiting_payment', DB::value('SELECT status FROM property_applications WHERE id = ?', [$app['id']]), 'awaiting payment');
    $h->get("/account/quotations/{$q['id']}");
    T::notContains('PayFast', $h->body, 'no online card payments');
    T::contains('Upload your proof of payment', $h->body, 'customer is asked for proof of payment');

    $ad->get("/admin/quotations/{$q['id']}");
    $ad->post("/admin/quotations/{$q['id']}/payment", ['amount' => '748.08', 'method' => 'eft', 'provider_reference' => 'FNB-123']);
    T::eq(74808, (int) DB::value('SELECT SUM(amount_cents) FROM payments WHERE quotation_id = ? AND status = "paid"', [$q['id']]), 'payment recorded');
    T::eq('approved', DB::value('SELECT status FROM property_applications WHERE id = ?', [$app['id']]), 'approved after full payment');
    $ad->post("/admin/applications/property/{$app['id']}/status", ['action' => 'activate']);
    T::eq('active', DB::value('SELECT status FROM property_applications WHERE id = ?', [$app['id']]), 'application active');
    T::eq('active', DB::value('SELECT protection_status FROM registered_properties WHERE id = ?', [$app['property_id']]), 'property protected');
    T::eq('client', DB::value('SELECT client_status FROM users WHERE id = ?', [$home['id']]), 'customer is now a client');
    $h->get('/account/panic');
    T::contains('At least one emergency contact', $h->body, 'still needs an emergency contact');
    $h->get('/account/contacts');
    $h->post('/account/contacts', ['name' => 'Thandi', 'relationship' => 'Sister', 'mobile' => '0825551234', 'notify_on_panic' => 1]);
    $h->get('/account/panic');
    T::contains('HOLD', $h->body, 'panic button unlocked');
});

T::test('Panic: complete-profile rule, confirmation, control room delivery, acknowledgement, dispatch, client status view', function () {
    $plain = T::login(T::user('homeowner'));
    $plain->get('/account/panic');
    $plain->post('/account/panic', ['confirm' => 1]);
    T::eq(403, $plain->status, 'incomplete profile cannot raise a panic alert');

    $client = T::user('homeowner');
    DB::run('UPDATE users SET client_status = "client" WHERE id = ?', [$client['id']]);
    T::completeProfile($client);
    $c = T::login($client);
    $c->get('/account/panic');
    $c->post('/account/panic', ['latitude' => '-26.1', 'longitude' => '28.05', 'accuracy' => '12', 'location_status' => 'captured']);
    T::eq(0, (int) DB::value('SELECT COUNT(*) FROM panic_alerts WHERE customer_id = ?', [$client['id']]), 'no alert without confirmation (accidental-activation protection)');
    $c->get('/account/panic');
    $c->post('/account/panic', ['confirm' => 1, 'latitude' => '-26.1', 'longitude' => '28.05', 'accuracy' => '12', 'location_status' => 'captured', 'silent' => 1, 'extra_info' => 'Intruder in garden']);
    $pa = DB::one('SELECT * FROM panic_alerts WHERE customer_id = ?', [$client['id']]);
    T::true($pa !== null, 'alert created');
    T::redirectsTo('/account/panic/' . $pa['reference'], $c, 'client sees the alert reference');
    T::eq('submitted', $pa['status'], 'submitted, not yet seen');
    T::eq('critical', DB::value('SELECT priority FROM incidents WHERE id = ?', [$pa['incident_id']]), 'critical incident created');

    $officer = T::user('security_staff');
    $s = T::login($officer);
    $s->get('/security/feed');
    $feed = json_decode($s->body, true);
    T::true(in_array($pa['reference'], array_column($feed['panics'], 'reference'), true), 'alert appears in the control room feed');
    T::eq('delivered', DB::value('SELECT status FROM panic_alerts WHERE id = ?', [$pa['id']]), 'shown on screen → delivered');
    $s->get('/security');
    $s->post("/security/incidents/{$pa['incident_id']}/acknowledge", ['return' => 'feed']);
    T::eq('acknowledged', DB::value('SELECT status FROM panic_alerts WHERE id = ?', [$pa['id']]), 'acknowledged');
    $s->get("/security/incidents/{$pa['incident_id']}");
    $s->post("/security/incidents/{$pa['incident_id']}/assign", ['officer_id' => $officer['id']]);
    T::eq('dispatched', DB::value('SELECT status FROM panic_alerts WHERE id = ?', [$pa['id']]), 'assigning an officer = dispatched');

    $c->get("/account/panic/{$pa['reference']}/status");
    $j = json_decode($c->body, true);
    T::eq('dispatched', $j['status'], 'client status endpoint shows dispatch');
    T::true($j['acknowledged_at'] !== null && $j['delivered_at'] !== null, 'client sees delivery and acknowledgement times');
    $c->post("/account/panic/{$pa['reference']}/location", ['latitude' => '-26.101', 'longitude' => '28.051'], csrf: true);
    T::eq(2, (int) DB::value('SELECT COUNT(*) FROM panic_locations WHERE panic_alert_id = ?', [$pa['id']]), 'live location update stored');

    $plain->get("/account/panic/{$pa['reference']}");
    T::eq(404, $plain->status, 'another homeowner cannot see the alert');
    $plain->get('/security/feed');
    T::eq(403, $plain->status, 'homeowners cannot read the control room feed');

    $c->post("/account/panic/{$pa['reference']}/cancel", ['reason' => 'false alarm']);
    T::eq('cancelled', DB::value('SELECT status FROM panic_alerts WHERE id = ?', [$pa['id']]), 'client can cancel');
});

T::test('Services marketplace: listing → request → quotation → acceptance → payment → confirmed booking', function () {
    $admin = T::login(T::user('admin'));
    $admin->get('/admin/services/new');
    $admin->post('/admin/services/new', ['name' => 'VIP escort test', 'category' => 'Specialised', 'description' => 'Close protection.', 'pricing' => 'from', 'price' => '1500', 'price_unit' => 'per hour', 'is_active' => 1]);
    $svc = DB::one('SELECT * FROM services WHERE name = "VIP escort test"');
    T::eq(150000, (int) $svc['price_cents'], 'service saved with price');
    $pub = new Http(T::HOST);
    $pub->get('/services');
    T::contains('VIP escort test', $pub->body, 'public services page lists it');

    $home = T::user('homeowner');
    $h = T::login($home);
    $h->get('/account/services/' . $svc['id']);
    $h->post("/account/services/{$svc['id']}/request", ['requested_date' => date('Y-m-d', strtotime('-1 day')), 'location_text' => 'Sandton']);
    T::eq(0, (int) DB::value('SELECT COUNT(*) FROM service_requests WHERE customer_id = ?', [$home['id']]), 'past dates refused');
    $h->get('/account/services/' . $svc['id']);
    $h->post("/account/services/{$svc['id']}/request", ['requested_date' => date('Y-m-d', strtotime('+3 days')), 'requested_time' => '18:00', 'location_text' => 'Sandton Convention Centre', 'duration_text' => '4 hours']);
    $r = DB::one('SELECT * FROM service_requests WHERE customer_id = ?', [$home['id']]);
    T::eq('submitted', $r['status'], 'request submitted');

    $admin->get("/admin/quotations/new/service/{$r['id']}");
    $admin->post("/admin/quotations/new/service/{$r['id']}", ['desc' => ['VIP escort, 4 hours'], 'qty' => ['4'], 'price' => ['1500'], 'billing' => 'once_off', 'send' => '1']);
    $q = DB::one('SELECT * FROM quotations WHERE subject_type = "service" AND subject_id = ?', [$r['id']]);
    T::eq(600000, (int) $q['total_cents'], 'no VAT when unticked');
    $h->get("/account/quotations/{$q['id']}");
    $h->post("/account/quotations/{$q['id']}/respond", ['decision' => 'accept']);
    T::eq('awaiting_payment', DB::value('SELECT status FROM service_requests WHERE id = ?', [$r['id']]), 'awaiting payment');
    $admin->get("/admin/quotations/{$q['id']}");
    $admin->post("/admin/quotations/{$q['id']}/payment", ['amount' => '6000', 'method' => 'card']);
    T::eq('confirmed', DB::value('SELECT status FROM service_requests WHERE id = ?', [$r['id']]), 'booking confirmed after payment');
    $h->get('/account/notifications');
    T::contains('booking is confirmed', $h->body, 'customer notified in the app');
});

T::test('Reports and Excel exports are admin-only, and exports neutralise spreadsheet formulas', function () {
    $evil = T::user('homeowner');
    DB::run('UPDATE user_profiles SET first_name = ? WHERE user_id = ?', ['=HYPERLINK("http://x")', $evil['id']]);
    $admin = T::login(T::user('admin'));
    $admin->get('/admin/reports?days=30');
    T::eq(200, $admin->status, 'reports page loads');
    $admin->get('/admin/reports/export/homeowners');
    T::contains('text/csv', $admin->headers['content-type'] ?? '', 'CSV download');
    T::contains("'=HYPERLINK", $admin->body, 'formula neutralised');
    $sec = T::login(T::user('security_staff'));
    $sec->get('/admin/reports/export/incidents');
    T::eq(403, $sec->status, 'security cannot export');
});

T::test('Smoke test: every page of all three portals loads', function () {
    $pages = [
        'homeowner' => ['/account', '/account/incidents', '/account/incidents/new', '/account/assets/property', '/account/assets/property/new',
            '/account/assets/vehicle', '/account/assets/vehicle/new', '/account/applications', '/account/services', '/account/requests',
            '/account/quotations', '/account/payments', '/account/contacts', '/account/notifications', '/account/profile', '/account/panic'],
        'admin' => ['/admin', '/admin/incidents', '/admin/incidents/new', '/admin/applications', '/admin/applications?status=all', '/admin/quotations',
            '/admin/requests', '/admin/requests?status=all', '/admin/services/new', '/admin/pricelist', '/admin/payments', '/admin/homeowners',
            '/admin/alerts', '/admin/leads', '/admin/patrols', '/admin/reports', '/admin/reports?days=365', '/admin/staff', '/admin/branding', '/admin/audit',
            '/admin/notifications', '/admin/profile'],
        'security_staff' => ['/security', '/security/feed', '/security/assignments', '/security/incidents', '/security/incidents/new',
            '/security/patrols', '/security/homeowners', '/security/notifications', '/security/profile'],
    ];
    foreach ($pages as $role => $list) {
        $c = T::login(T::user($role));
        foreach ($list as $p) {
            $c->get($p);
            T::eq(200, $c->status, "$role: $p loads");
            T::notContains('Something went wrong', $c->body, "$role: $p has no error");
            T::true(!preg_match('/<b>(Warning|Notice|Deprecated|Fatal error)<\/b>|^(Warning|Notice|Deprecated): /m', $c->body), "$role: $p shows no PHP warning");
        }
    }
    $pub = new Http(T::HOST);
    foreach (['/', '/services', '/privacy', '/terms', '/login', '/register'] as $p) {
        $pub->get($p);
        T::eq(200, $pub->status, "public: $p loads");
    }
});

T::test('Play Store readiness: manifest, service worker, asset links, account deletion', function () {
    $c = new Http(T::HOST);
    $c->get('/manifest.webmanifest');
    $m = json_decode($c->body, true);
    T::eq(200, $c->status, 'manifest served');
    T::eq('standalone', $m['display'] ?? null, 'installable as a standalone app');
    T::true(count(array_filter($m['icons'], fn($i) => $i['sizes'] === '512x512')) >= 2, '512px icon and maskable icon');
    $c->get('/sw.js');
    T::eq(200, $c->status, 'service worker served');
    $c->get('/assets/icons/icon-512.png');
    T::eq(200, $c->status, 'icon served');
    $c->get('/.well-known/assetlinks.json');
    T::eq('[]', trim($c->body), 'asset links empty until the Android app fingerprint is configured');
    $c->get('/delete-account');
    T::contains('Delete your account', $c->body, 'public deletion page');
    $u = T::user('homeowner');
    $h = T::login($u);
    $h->get('/delete-account');
    $h->post('/delete-account', []);
    T::eq(0, (int) DB::value('SELECT COUNT(*) FROM audit_logs WHERE action = "user.deletion_requested" AND entity_id = ?', [(string) $u['id']]), 'confirmation required');
    $h->get('/delete-account');
    $h->post('/delete-account', ['confirm' => 1, 'reason' => 'Moving away']);
    T::eq(1, (int) DB::value('SELECT COUNT(*) FROM audit_logs WHERE action = "user.deletion_requested" AND entity_id = ?', [(string) $u['id']]), 'deletion request recorded');
    T::true((int) DB::value('SELECT COUNT(*) FROM notifications WHERE subject = "Account deletion request"') >= 0, 'admins notified');
});

T::test('Safety alerts appear publicly; patrol check-ins are logged by security and visible to admin', function () {
    $admin = T::login(T::user('admin'));
    $admin->get('/admin/alerts');
    $admin->post('/admin/alerts', ['title' => 'Suspicious white van', 'body' => 'Seen on 5th Ave tonight.', 'level' => 'warning', 'days' => 2]);
    $pub = new Http(T::HOST);
    $pub->get('/');
    T::contains('Suspicious white van', $pub->body, 'alert on public home page');

    $sec = T::login(T::user('security_staff'));
    $sec->get('/security/patrols');
    $sec->post('/security/patrols', ['area' => 'Oak Avenue', 'checkpoint' => 'Gate 2', 'notes' => 'All quiet']);
    T::eq(1, (int) DB::value('SELECT COUNT(*) FROM patrol_logs WHERE area = "Oak Avenue"'), 'patrol logged');
    $admin->get('/admin/patrols');
    T::contains('Oak Avenue', $admin->body, 'admin sees patrol log');
    $home = T::login(T::user('homeowner'));
    $home->get('/security/patrols');
    T::eq(403, $home->status, 'homeowners cannot see patrols');
});

T::test('Community members with a complete profile can press panic; they show up as leads', function () {
    $member = T::user('homeowner');
    T::completeProfile($member);
    $m = T::login($member);
    $m->get('/account/panic');
    T::contains('data-panic-hold', $m->body, 'panic button shown to a community member with a complete profile');
    T::contains("You're a community member", $m->body, 'explains what a community member gets');
    $m->post('/account/panic', ['confirm' => 1, 'location_status' => 'denied']);
    $pa = DB::one('SELECT * FROM panic_alerts WHERE customer_id = ?', [$member['id']]);
    T::true($pa !== null, 'community member alert created');

    $s = T::login(T::user('security_staff'));
    $s->get('/security/feed');
    $feed = json_decode($s->body, true);
    $row = array_values(array_filter($feed['panics'], fn($p) => $p['reference'] === $pa['reference']))[0] ?? null;
    T::true($row !== null && (int) $row['is_client'] === 0, 'control room sees it is not a client');

    $admin = T::login(T::user('admin'));
    $admin->get('/admin/leads');
    T::contains('/admin/leads/' . $member['id'] . '/offer', $admin->body, 'member is listed as a lead');
    $admin->get("/admin/incidents/{$pa['incident_id']}");
    T::contains('Send protection offer', $admin->body, 'incident page offers to send a protection offer');
    $admin->post('/admin/leads/' . $member['id'] . '/offer', ['return' => "/admin/incidents/{$pa['incident_id']}"]);
    T::redirectsTo("/admin/incidents/{$pa['incident_id']}", $admin, 'back to the incident');
    T::eq(1, (int) DB::value('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND subject LIKE "Protect your home%"', [$member['id']]), 'offer delivered in the app');
    $admin->get('/admin/leads');
    $admin->post('/admin/leads/' . $member['id'] . '/offer', ['return' => 'https://evil.example']);
    T::redirectsTo('/admin/leads', $admin, 'unsafe return link ignored');
    T::eq(1, (int) DB::value('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND subject LIKE "Protect your home%"', [$member['id']]), 'no repeat offer within 14 days');

    $sec = T::login(T::user('security_staff'));
    $sec->get('/security');
    $sec->post('/admin/leads/' . $member['id'] . '/offer', []);
    T::eq(403, $sec->status, 'security staff cannot send offers');
});

T::test('Price list: admin edits every price in one place; public and homeowners see it', function () {
    $tid = T::$companyId;
    $mk = fn(string $name, string $cat) => DB::one('SELECT * FROM services WHERE id = ?', [DB::insert('services', ['tenant_id' => $tid, 'name' => $name, 'category' => $cat, 'description' => 'Test service.', 'pricing' => 'quote'])]);
    $armed = $mk('Armed response test', 'Residential');
    $cctv = $mk('CCTV install test', 'Installations');
    $before = (int) DB::value('SELECT COUNT(*) FROM services WHERE tenant_id = ?', [$tid]);
    $admin = T::login(T::user('admin'));
    $admin->get('/admin/pricelist');
    T::eq(200, $admin->status, 'admin opens the price list');
    T::contains('name="price[' . $armed['id'] . ']"', $admin->body, 'every service is editable');

    $admin->post('/admin/pricelist', ['pricing' => [$armed['id'] => 'fixed', $cctv['id'] => 'from'], 'price' => [$armed['id'] => '450', $cctv['id'] => ''],
        'unit' => [$armed['id'] => 'per month'], 'active' => [$armed['id'] => 1, $cctv['id'] => 1], 'sort' => [$armed['id'] => 1, $cctv['id'] => 2]]);
    T::eq('quote', DB::value('SELECT pricing FROM services WHERE id = ?', [$armed['id']]), 'a missing price rejects the whole save');

    $admin->get('/admin/pricelist');
    $admin->post('/admin/pricelist', ['pricing' => [$armed['id'] => 'fixed', $cctv['id'] => 'from'], 'price' => [$armed['id'] => '450', $cctv['id'] => 'R 3 500,00'],
        'unit' => [$armed['id'] => 'per month', $cctv['id'] => 'once-off'], 'active' => [$armed['id'] => 1], 'sort' => [$armed['id'] => 1, $cctv['id'] => 2]]);
    T::eq(45000, (int) DB::value('SELECT price_cents FROM services WHERE id = ?', [$armed['id']]), 'armed response priced');
    T::eq(350000, (int) DB::value('SELECT price_cents FROM services WHERE id = ?', [$cctv['id']]), 'CCTV priced from R3 500');
    T::eq(0, (int) DB::value('SELECT is_active FROM services WHERE id = ?', [$cctv['id']]), 'unticked service hidden');
    T::eq($before, (int) DB::value('SELECT COUNT(*) FROM services WHERE tenant_id = ?', [$tid]), 'no service was deleted');

    $pub = new Http(T::HOST);
    $pub->get('/services');
    T::contains('R450.00', $pub->body, 'public price list shows the new price');
    T::notContains('CCTV install test', $pub->body, 'hidden service not listed publicly');

    $home = T::user('homeowner');
    $h = T::login($home);
    $h->get('/account');
    T::contains('My protection', $h->body, 'dashboard shows the protection card');
    T::contains('Add a business', $h->body, 'with the three plans');
    T::completeProfile($home);
    $h->get('/account');
    T::contains('Get protected', $h->body, 'unprotected home gets a "Get protected" button');
    $h->post('/admin/pricelist', ['pricing' => [$armed['id'] => 'fixed'], 'price' => [$armed['id'] => '1']]);
    T::eq(403, $h->status, 'homeowners cannot change prices');
    DB::run('DELETE FROM services WHERE id IN (?, ?)', [$armed['id'], $cctv['id']]);
});

T::test('Soshanguve area: landing, control room map and address defaults; owners can edit their home', function () {
    $pub = new Http(T::HOST);
    $pub->get('/');
    T::contains('Soshanguve community', $pub->body, 'landing page names the community');
    $s = T::login(T::user('security_staff'));
    $s->get('/security');
    T::contains('data-lat="-25.523"', $s->body, 'control room map starts over Soshanguve');

    $home = T::user('homeowner');
    $h = T::login($home);
    $h->get('/account/assets/property/new');
    T::contains('value="Pretoria"', $h->body, 'city defaults to Pretoria');
    T::contains('Soshanguve Block H', $h->body, 'suburb example is in Soshanguve');
    T::completeProfile($home);
    $pid = (int) DB::value('SELECT id FROM registered_properties WHERE owner_id = ?', [$home['id']]);
    $h->get("/account/assets/property/$pid/edit");
    T::contains('value="1 Test St"', $h->body, 'edit form is filled in');
    $h->post("/account/assets/property/$pid", ['name' => 'Home', 'property_type' => 'House', 'address' => '1452 Block L', 'suburb' => 'Soshanguve Block L', 'city' => 'Pretoria']);
    T::redirectsTo('/account/assets/property', $h, 'saved');
    T::eq('Soshanguve Block L', DB::value('SELECT suburb FROM registered_properties WHERE id = ?', [$pid]), 'address updated');

    $other = T::login(T::user('homeowner'));
    $other->get("/account/assets/property/$pid/edit");
    T::eq(404, $other->status, 'cannot open someone else\'s property');
    $other->get('/account');
    $other->post("/account/assets/property/$pid", ['name' => 'X', 'property_type' => 'House', 'address' => 'x', 'suburb' => 'x', 'city' => 'x']);
    T::eq(404, $other->status, 'cannot change someone else\'s property');
    T::eq('1452 Block L', DB::value('SELECT address FROM registered_properties WHERE id = ?', [$pid]), 'unchanged');
});

T::test('Protection plans: home, business and vehicle each get the right monthly plan; quotation is pre-priced', function () {
    $home = T::user('homeowner');
    $h = T::login($home);
    $h->get('/account/assets/property/new?type=business');
    T::contains('Business alarm plan', $h->body, 'business form shows the business plan');
    $h->post('/account/assets/property', ['name' => 'Spaza', 'property_type' => 'Business premises', 'address' => '12 Block H', 'suburb' => 'Soshanguve Block H', 'city' => 'Pretoria',
        'apply' => 1, 'extras' => ['Not a real extra']]);
    $app = DB::one('SELECT * FROM property_applications WHERE applicant_id = ? ORDER BY id DESC', [$home['id']]);
    T::eq(['Business alarm plan'], json_decode($app['services_requested'], true), 'business gets the business alarm plan, nothing else');

    $h->get('/account/assets/property/new');
    $h->post('/account/assets/property', ['name' => 'Home', 'property_type' => 'House', 'address' => '1 Block L', 'suburb' => 'Soshanguve Block L', 'city' => 'Pretoria', 'apply' => 1]);
    $homeApp = DB::one('SELECT * FROM property_applications WHERE applicant_id = ? ORDER BY id DESC', [$home['id']]);
    T::eq(['Home protection plan'], json_decode($homeApp['services_requested'], true), 'a house gets the home plan');

    $h->get('/account/assets/vehicle/new');
    $h->post('/account/assets/vehicle', ['make' => 'Toyota', 'model' => 'Corolla', 'registration' => 'ab 12 cd gp', 'colour' => 'White', 'apply' => 1]);
    $vApp = DB::one('SELECT * FROM vehicle_applications WHERE applicant_id = ? ORDER BY id DESC', [$home['id']]);
    T::eq(['Vehicle tracking plan'], json_decode($vApp['services_requested'], true), 'a car gets the tracker plan');

    $admin = T::login(T::user('admin'));
    $admin->get("/admin/quotations/new/vehicle/{$vApp['id']}");
    T::contains('Vehicle tracking plan (per month)', $admin->body, 'quotation pre-filled with the plan');
    T::contains('value="199.00"', $admin->body, 'monthly price filled in');
    T::contains('Tracker supply and fitting', $admin->body, 'once-off fitting fee added');
    T::contains('value="1500.00"', $admin->body, 'fitting price filled in');

    $plan = DB::one('SELECT * FROM services WHERE tenant_id = ? AND plan_for = "home"', [T::$companyId]);
    $h->get('/account/services/' . $plan['id']);
    T::eq(404, $h->status, 'plans are not booked in the services marketplace');
    $pub = new Http(T::HOST);
    $pub->get('/');
    T::contains('Protection plans', $pub->body, 'landing page shows the plans');
    $pub->get('/services');
    T::contains('Monthly protection', $pub->body, 'services page leads with the plans');
});

T::test('Panic call-back: control room calls back on WhatsApp and the person sees it', function () {
    $member = T::user('homeowner');
    T::completeProfile($member);
    $m = T::login($member);
    $m->get('/account/panic');
    $m->post('/account/panic', ['confirm' => 1, 'location_status' => 'denied']);
    $pa = DB::one('SELECT * FROM panic_alerts WHERE customer_id = ?', [$member['id']]);
    $m->get('/account/panic/' . $pa['reference']);
    T::contains('call you back on WhatsApp', $m->body, 'person is told to expect a WhatsApp call-back');

    $s = T::login(T::user('security_staff'));
    $s->get('/security');
    $s->post("/security/incidents/{$pa['incident_id']}/callback", ['method' => 'whatsapp']);
    T::eq(302, $s->status, 'redirects to WhatsApp');
    T::true(str_starts_with($s->headers['location'] ?? '', 'https://wa.me/' . wa_number($member['mobile'])), 'opens WhatsApp on the person\'s number');
    $row = DB::one('SELECT * FROM panic_alerts WHERE id = ?', [$pa['id']]);
    T::true($row['callback_at'] !== null && $row['callback_method'] === 'whatsapp', 'call-back recorded');
    T::eq('acknowledged', $row['status'], 'calling back also acknowledges the alert');

    $m->get("/account/panic/{$pa['reference']}/status");
    $j = json_decode($m->body, true);
    T::true($j['callback_at'] !== null, 'person sees the call-back time');

    $other = T::login(T::user('homeowner'));
    $other->get('/account');
    $other->post("/security/incidents/{$pa['incident_id']}/callback", []);
    T::eq(403, $other->status, 'homeowners cannot trigger call-backs');
});

T::test('EFT proof of payment: customer uploads, admin approves or rejects, protection follows', function () {
    $home = T::user('homeowner');
    $h = T::login($home);
    $h->get('/account/assets/property/new');
    $h->post('/account/assets/property', ['name' => 'Home', 'property_type' => 'House', 'address' => '9 Block L', 'suburb' => 'Soshanguve Block L', 'city' => 'Pretoria', 'apply' => 1]);
    $app = DB::one('SELECT * FROM property_applications WHERE applicant_id = ?', [$home['id']]);
    $admin = T::login(T::user('admin'));
    $admin->get("/admin/quotations/new/property/{$app['id']}");
    $admin->post("/admin/quotations/new/property/{$app['id']}", ['desc' => ['Home protection plan (per month)'], 'qty' => ['1'], 'price' => ['450'], 'billing' => 'monthly',
        'valid_until' => date('Y-m-d', strtotime('+14 days')), 'send' => '1']);
    $q = DB::one('SELECT * FROM quotations WHERE subject_type = "property" AND subject_id = ?', [$app['id']]);
    $h->get("/account/quotations/{$q['id']}");
    $h->post("/account/quotations/{$q['id']}/respond", ['decision' => 'accept']);

    $h->get("/account/quotations/{$q['id']}");
    $h->post("/account/quotations/{$q['id']}/proof", ['amount' => '450']);
    T::eq(0, (int) DB::value('SELECT COUNT(*) FROM payments WHERE quotation_id = ?', [$q['id']]), 'no proof without a file');

    $pdf = tempnam(sys_get_temp_dir(), 'pop');
    file_put_contents($pdf, "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n");
    $h->get("/account/quotations/{$q['id']}");
    $h->post("/account/quotations/{$q['id']}/proof", ['amount' => '450.00', 'bank_reference' => $q['reference'], 'pop[0]' => new CURLFile($pdf, 'application/pdf', 'pop.pdf')]);
    $pm = DB::one('SELECT * FROM payments WHERE quotation_id = ?', [$q['id']]);
    T::true($pm !== null && $pm['status'] === 'pending', 'proof saved, waiting to be checked');
    T::eq('awaiting_payment', DB::value('SELECT status FROM property_applications WHERE id = ?', [$app['id']]), 'not paid until the admin approves');
    $file = (int) DB::value('SELECT id FROM files WHERE owner_type = "payment" AND owner_id = ?', [$pm['id']]);
    $h->get("/files/$file");
    T::eq(200, $h->status, 'customer can open their own proof');
    $other = T::login(T::user('homeowner'));
    $other->get("/files/$file");
    T::eq(403, $other->status, 'other homeowners cannot');

    $admin->get('/admin/payments');
    T::contains('Proofs of payment to check', $admin->body, 'admin sees proofs to check');
    T::contains("/admin/payments/{$pm['id']}/review", $admin->body, 'with approve / reject');
    $admin->post("/admin/payments/{$pm['id']}/review", ['decision' => 'reject', 'reason' => 'not in our account yet']);
    T::eq('failed', DB::value('SELECT status FROM payments WHERE id = ?', [$pm['id']]), 'rejected');
    $h->get("/account/quotations/{$q['id']}");
    T::contains('not in our account yet', $h->body, 'customer sees why');

    $h->post("/account/quotations/{$q['id']}/proof", ['amount' => '450.00', 'pop[0]' => new CURLFile($pdf, 'application/pdf', 'pop.pdf')]);
    $pm2 = DB::one('SELECT * FROM payments WHERE quotation_id = ? AND status = "pending"', [$q['id']]);
    $admin->get('/admin/payments');
    $admin->post("/admin/payments/{$pm2['id']}/review", ['decision' => 'approve']);
    T::eq('paid', DB::value('SELECT status FROM payments WHERE id = ?', [$pm2['id']]), 'approved');
    T::eq('approved', DB::value('SELECT status FROM property_applications WHERE id = ?', [$app['id']]), 'application approved once paid');
    @unlink($pdf);
});

T::test('Simple and no duplicates: one admin screen, one customer page, monthly plans first then once-off', function () {
    $tid = T::$companyId;
    $guards = (int) DB::insert('services', ['tenant_id' => $tid, 'name' => 'Guards T', 'category' => 'Guarding', 'description' => 'Test.', 'pricing' => 'from', 'price_cents' => 90000, 'price_unit' => 'per shift']);
    $hidden = (int) DB::insert('services', ['tenant_id' => $tid, 'name' => 'Alarm monitoring T', 'category' => 'Add-ons', 'description' => 'Test.', 'pricing' => 'quote', 'is_active' => 0]);

    $admin = T::login(T::user('admin'));
    $admin->get('/admin/services');
    T::eq(301, $admin->status, 'old services list is gone');
    T::redirectsTo('/admin/pricelist', $admin, 'it moved to Services & prices');
    $admin->get('/admin/pricelist');
    T::true(strpos($admin->body, 'Monthly plans') < strpos($admin->body, 'Once-off services'), 'admin: monthly plans first, once-off below');
    T::notContains('Extra on', $admin->body, 'no extras to manage');

    $pub = new Http(T::HOST);
    $pub->get('/pricelist');
    T::eq(301, $pub->status, 'old price list address');
    T::redirectsTo('/services', $pub, 'goes to the one services page');
    $pub->get('/services');
    T::true(strpos($pub->body, 'Monthly protection') < strpos($pub->body, 'When you need us'), 'customer: monthly on top, once-off at the bottom');
    T::contains('Guards T', $pub->body, 'once-off personnel service listed with a Book button');
    T::notContains('Alarm monitoring T', $pub->body, 'hidden services stay hidden');

    $home = T::user('homeowner');
    $h = T::login($home);
    $h->get('/account/assets/property/new');
    T::notContains('Optional extras', $h->body, 'no extras when signing up');
    $h->get('/account/services/' . $hidden);
    T::eq(404, $h->status, 'hidden services cannot be booked');
    $h->get('/account/services/' . $guards);
    T::eq(200, $h->status, 'once-off services can be booked');
    DB::run('DELETE FROM services WHERE id IN (?, ?)', [$guards, $hidden]);
});
