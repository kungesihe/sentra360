<?php
declare(strict_types=1);
/**
 * Adds realistic DEMONSTRATION activity (incidents, a live panic alert,
 * applications at every stage, quotations, bookings, payments, alerts,
 * patrols) for screenshots and sales demos. Refuses to run in production.
 *
 *   php bin/install.php --fresh --demo && php bin/demo_activity.php
 */
if (PHP_SAPI !== 'cli') {
    exit("CLI only\n");
}
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\DB;
use App\Core\TenantContext;
use App\Services\Incidents;
use App\Services\Ops;
use App\Services\Workflow;

if (config('env') === 'production') {
    exit("Refusing to add demo activity in production.\n");
}
$tid = TenantContext::id();
$uid = fn(string $email) => (int) DB::value('SELECT id FROM users WHERE email = ?', [$email]);
$user = fn(string $email) => DB::one('SELECT u.*, p.first_name, p.surname FROM users u JOIN user_profiles p ON p.user_id = u.id WHERE u.email = ?', [$email]);
$cat = fn(string $code) => (int) DB::value('SELECT id FROM incident_categories WHERE code = ?', [$code]);
$ago = fn(string $rel) => date('Y-m-d H:i:s', strtotime($rel));

$lerato = $uid('lerato@demo.test');
$sipho = $uid('sipho@demo.test');
$anika = $uid('anika@demo.test');
$officer = $uid('officer@demo.test');
$admin = $uid('admin@demo.test');

DB::update('tenant_settings', [
    'display_name' => 'Ubuntu Shield Security',
    'welcome_message' => 'Protecting Soshanguve, block by block, 24/7.',
    'primary_color' => '#0E5E6F', 'secondary_color' => '#0B1F2A',
    'contact_phone' => '011 555 0100', 'contact_email' => 'hello@ubuntushield.example',
    'payment_instructions' => "Bank: First National Bank\nAccount name: Ubuntu Shield Security (Pty) Ltd\nAccount number: 62000000000 (demo)\nBranch code: 250655\nReference: your quotation number",
    'whatsapp_number' => '082 123 4567',
], ['tenant_id' => $tid]);
DB::update('tenants', ['name' => 'Ubuntu Shield Security'], ['id' => $tid]);
TenantContext::refresh();

// ---------------------------------------------------------------- properties & vehicles
$prop1 = DB::insert('registered_properties', ['tenant_id' => $tid, 'owner_id' => $lerato, 'name' => 'Home', 'property_type' => 'House', 'address' => '1452 Block L', 'suburb' => 'Soshanguve Block L', 'city' => 'Pretoria',
    'latitude' => -25.5181, 'longitude' => 28.1003, 'existing_security' => 'Alarm with outdoor beams, electric fence', 'access_requirements' => 'Ring at the green gate; two dogs in the yard', 'security_concerns' => 'Two break-ins in the street this month', 'protection_status' => 'active', 'activated_at' => $ago('-40 days')]);
$veh1 = DB::insert('registered_vehicles', ['tenant_id' => $tid, 'owner_id' => $lerato, 'make' => 'Toyota', 'model' => 'Fortuner', 'registration' => 'LK 22 PS GP', 'colour' => 'White', 'year' => 2021, 'primary_driver' => 'Lerato Khumalo', 'protection_status' => 'none']);
$prop2 = DB::insert('registered_properties', ['tenant_id' => $tid, 'owner_id' => $sipho, 'name' => 'Nkosi Spaza Shop', 'property_type' => 'Business premises', 'address' => '6630 Block H', 'suburb' => 'Soshanguve Block H', 'city' => 'Pretoria', 'existing_security' => 'Basic alarm', 'protection_status' => 'none']);
$prop3 = DB::insert('registered_properties', ['tenant_id' => $tid, 'owner_id' => $anika, 'name' => 'Apartment', 'property_type' => 'Apartment / flat', 'address' => 'Unit 12, 210 Block GG', 'suburb' => 'Soshanguve Block GG', 'city' => 'Pretoria', 'protection_status' => 'none']);
DB::run('UPDATE users SET client_status = "client", status = "active" WHERE id IN (?, ?)', [$lerato, $anika]);

$mkApp = function (string $kind, int $assetId, int $applicant, array $services, string $status, string $created) use ($tid) {
    [$table, , $fk, $prefix] = Workflow::KINDS[$kind];
    $id = DB::insert($table, ['tenant_id' => $tid, 'reference' => Ops::reference($prefix, $table), $fk => $assetId, 'applicant_id' => $applicant,
        'services_requested' => json_encode($services), 'status' => $status, 'submitted_at' => $created, 'created_at' => $created]);
    DB::insert('workflow_events', ['tenant_id' => $tid, 'subject_type' => $kind . '_application', 'subject_id' => $id, 'to_status' => 'submitted', 'note' => 'Application submitted.', 'created_at' => $created]);
    return $id;
};
$appActive = $mkApp('property', $prop1, $lerato, ['Home protection plan'], 'active', $ago('-45 days'));
$appVeh = $mkApp('vehicle', $veh1, $lerato, ['Vehicle tracking plan'], 'submitted', $ago('-2 hours'));
$appSipho = $mkApp('property', $prop2, $sipho, ['Business alarm plan'], 'submitted', $ago('-1 day'));
$appAnika = $mkApp('property', $prop3, $anika, ['Home protection plan'], 'submitted', $ago('-3 days'));

// Quotation sent to Sipho (awaiting acceptance)
$q1 = Workflow::createQuotation('property', Workflow::application('property', $appSipho), [
    ['description' => 'Business alarm plan (per month)', 'quantity' => 1, 'unit_cents' => 85000],
    ['description' => 'Alarm supply and installation (once-off, first payment only)', 'quantity' => 1, 'unit_cents' => 350000],
], 'monthly', date('Y-m-d', strtotime('+14 days')), 'Includes free security assessment. 12-month agreement.', true, true, $admin);
// Anika: quotation accepted and paid, waiting for activation
$q2 = Workflow::createQuotation('property', Workflow::application('property', $appAnika), [
    ['description' => 'Home protection plan (per month)', 'quantity' => 1, 'unit_cents' => 45000],
], 'monthly', date('Y-m-d', strtotime('+14 days')), null, true, true, $admin);
Workflow::respond(Workflow::quotation($q2), true);
Workflow::recordPayment($anika, Workflow::quotation($q2), (int) DB::value('SELECT total_cents FROM quotations WHERE id = ?', [$q2]), 'eft', 'FNB 0098123', 'First month', $admin);
DB::run('UPDATE property_applications SET status = "approved" WHERE id = ?', [$appAnika]);

// ---------------------------------------------------------------- services & bookings
$vip = (int) DB::value('SELECT id FROM services WHERE name = "VIP escort"');
DB::update('services', ['pricing' => 'from', 'price_cents' => 85000, 'price_unit' => 'per hour'], ['id' => $vip]);
$event = (int) DB::value('SELECT id FROM services WHERE name = "Private event security"');
DB::update('services', ['pricing' => 'from', 'price_cents' => 350000, 'price_unit' => 'per event'], ['id' => $event]);
$guards = (int) DB::value('SELECT id FROM services WHERE name = "On-site guards"');
DB::update('services', ['pricing' => 'from', 'price_cents' => 90000, 'price_unit' => 'per shift'], ['id' => $guards]);
$req1 = DB::insert('service_requests', ['tenant_id' => $tid, 'reference' => Ops::reference('SRV', 'service_requests'), 'service_id' => $event, 'customer_id' => $lerato,
    'requested_date' => date('Y-m-d', strtotime('next saturday')), 'requested_time' => '18:00:00', 'location_text' => '1452 Block L, Soshanguve', 'duration_text' => '18:00 to 00:00', 'instructions' => '50th birthday party, about 80 guests. Need 3 guards and parking control.', 'status' => 'submitted']);
Ops::event('service_request', $req1, null, 'submitted', 'Request submitted.');
$req2 = DB::insert('service_requests', ['tenant_id' => $tid, 'reference' => Ops::reference('SRV', 'service_requests'), 'service_id' => $vip, 'customer_id' => $sipho,
    'requested_date' => date('Y-m-d', strtotime('+5 days')), 'requested_time' => '07:30:00', 'location_text' => 'OR Tambo International → Soshanguve Block L', 'duration_text' => '3 hours', 'status' => 'submitted']);
$q3 = Workflow::createQuotation('service', DB::one('SELECT * FROM service_requests WHERE id = ?', [$req2]), [['description' => 'VIP escort, 3 hours, 1 armed officer and vehicle', 'quantity' => 3, 'unit_cents' => 85000]], 'once_off', date('Y-m-d', strtotime('+4 days')), null, true, true, $admin);
Workflow::respond(Workflow::quotation($q3), true);
Workflow::recordPayment($sipho, Workflow::quotation($q3), (int) DB::value('SELECT total_cents FROM quotations WHERE id = ?', [$q3]), 'card', 'Card machine', null, $admin);

// ---------------------------------------------------------------- incidents
$inc = function (array $d, ?int $reporter, string $created) {
    $i = Incidents::create($d, $reporter);
    DB::run('UPDATE incidents SET created_at = ? WHERE id = ?', [$created, $i['id']]);
    DB::run('UPDATE workflow_events SET created_at = ? WHERE subject_type = "incident" AND subject_id = ?', [$created, $i['id']]);
    return Incidents::find((int) $i['id']);
};
$i1 = $inc(['category_id' => $cat('suspicious_activity'), 'occurred_at' => $ago('-25 minutes'), 'location_text' => 'Near the tuck shop, Block L, Soshanguve', 'latitude' => -25.5189, 'longitude' => 28.1012,
    'description' => 'Grey sedan without plates driving slowly, stopped twice outside houses. Two men inside.', 'witness_details' => 'Neighbour at no. 18 also saw it.'], $sipho, $ago('-20 minutes'));
$i2 = $inc(['category_id' => $cat('house_break_in'), 'occurred_at' => $ago('-1 day 02:10'), 'location_text' => '1471 Block L, Soshanguve', 'latitude' => -25.5172, 'longitude' => 28.0991,
    'description' => 'Back door forced open overnight. Laptop and TV taken.', 'injuries' => 'None'], $lerato, $ago('-1 day'));
Incidents::setStatus($i2, 'received', 'Received by the control room.');
$i2 = Incidents::find((int) $i2['id']);
Incidents::assign($i2, $officer);
$a = DB::one('SELECT * FROM incident_assignments WHERE incident_id = ?', [$i2['id']]);
Incidents::officerUpdate($a, Incidents::find((int) $i2['id']), 'on_scene', 'Scene secured, SAPS called.');
$i2 = Incidents::find((int) $i2['id']);
DB::update('incidents', ['resolution' => 'Premises secured, SAPS case number issued, client advised on door reinforcement.'], ['id' => $i2['id']]);
Incidents::setStatus($i2, 'resolved', 'Premises secured. SAPS case number issued.');
$i3 = $inc(['category_id' => $cat('mva'), 'occurred_at' => $ago('-3 hours'), 'location_text' => 'Aubrey Matlakala St near Soshanguve Crossing', 'latitude' => -25.5390, 'longitude' => 28.0950,
    'description' => 'Two cars collided at the traffic light. One driver seems hurt.', 'injuries' => 'One person with a head injury', 'assistance_required' => 1], $anika, $ago('-3 hours'));
Incidents::setStatus($i3, 'received', null);
Incidents::assign(Incidents::find((int) $i3['id']), $officer);
$i4 = $inc(['category_id' => $cat('road_hazard'), 'occurred_at' => $ago('-6 hours'), 'location_text' => 'Main road at Block H four-way stop, Soshanguve', 'description' => 'Traffic lights out, cars driving through.'], $lerato, $ago('-6 hours'));
Ops::event('incident', (int) $i3['id'], 'assigned', 'assigned', 'Ambulance called by officer.', true);

// Live panic alert (Lerato, at home, GPS)
$_SESSION = [];
$pa = Incidents::triggerPanic($user('lerato@demo.test') + [], ['latitude' => -25.5181, 'longitude' => 28.1004, 'accuracy_m' => 9, 'location_status' => 'captured',
    'property_id' => $prop1, 'vehicle_id' => null, 'silent' => true, 'extra_info' => 'Someone is climbing over the back wall', 'notify_contacts' => false]);
DB::run('UPDATE panic_alerts SET created_at = ? WHERE id = ?', [$ago('-2 minutes'), $pa['id']]);

// ---------------------------------------------------------------- other
DB::insert('emergency_contacts', ['tenant_id' => $tid, 'user_id' => $lerato, 'name' => 'Thabo Khumalo', 'relationship' => 'Husband', 'mobile' => '+27821230000', 'notify_on_panic' => 1]);
DB::insert('safety_alerts', ['tenant_id' => $tid, 'title' => 'Suspicious grey sedan in Block L', 'body' => 'A grey sedan without number plates has been seen following residents home. Please keep gates closed and report sightings.', 'level' => 'warning', 'area' => 'Soshanguve Block L', 'published_by' => $admin, 'expires_at' => $ago('+5 days')]);
foreach ([['Block L', 'Tuck shop corner', 'All quiet', '-40 minutes'], ['Block H', 'Taxi rank', 'Two loiterers moved on', '-2 hours'], ['Block GG', null, 'Street light out near the school', '-5 hours']] as [$area, $cp, $notes, $when]) {
    DB::insert('patrol_logs', ['tenant_id' => $tid, 'officer_id' => $officer, 'area' => $area, 'checkpoint' => $cp, 'notes' => $notes, 'latitude' => -25.52, 'longitude' => 28.10, 'created_at' => $ago($when)]);
}
echo "Demo activity added: 4 incidents, 1 live panic alert, 4 applications, 3 quotations, 2 bookings, payments, alert, patrols.\n";
