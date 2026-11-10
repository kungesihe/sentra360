<?php
declare(strict_types=1);

use App\Core\DB;
use Tests\Http;
use Tests\T;

$restore = function (): void {
    DB::run('UPDATE tenant_settings SET display_name = "Test Security Co", logo_path = NULL, logo_data = NULL, logo_mime = NULL, primary_color = "#0B3D91", secondary_color = "#111827"');
};

T::test('Branding changes appear on the public site and are escaped', function () use ($restore) {
    $c = T::login(T::user('admin'));
    $c->get('/admin/branding');
    $c->post('/admin/branding', [
        'display_name' => '<script>alert(1)</script> Guard', 'primary_color' => '#AA1122', 'secondary_color' => '#101010',
        'welcome_message' => 'Hello Rivonia', 'services_offered' => "Armed response\nCCTV", 'emergency_phone' => '0800 111 222',
    ]);
    T::redirectsTo('/admin/branding', $c, 'branding saved');
    $pub = new Http(T::HOST);
    $pub->get('/');
    T::contains('--brand:#AA1122', $pub->body, 'new colour applied');
    T::contains('Hello Rivonia', $pub->body, 'welcome message shown');
    T::contains('&lt;script&gt;alert(1)&lt;/script&gt; Guard', $pub->body, 'name is HTML-escaped');
    T::notContains('<script>alert(1)</script>', $pub->body, 'no raw script tag');
    T::contains('0800 111 222', $pub->body, 'emergency number shown');
    $pub->get('/security/login');
    T::contains('--brand:#AA1122', $pub->body, 'branding also applies to the login pages');
    $restore();
});

T::test('A real PNG logo is accepted and shown', function () use ($restore) {
    $c = T::login(T::user('admin'));
    $png = tempnam(sys_get_temp_dir(), 'logo');
    // 1x1 transparent PNG
    file_put_contents($png, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));
    $c->get('/admin/branding');
    $c->post('/admin/branding', ['display_name' => 'Logo Co', 'primary_color' => '#000000', 'secondary_color' => '#000000', 'logo' => new \CURLFile($png, 'image/png', 'logo.png')]);
    unlink($png);
    $path = (string) DB::value('SELECT logo_path FROM tenant_settings LIMIT 1');
    T::true(str_starts_with($path, '/logo?v='), 'logo stored in the database and served from /logo');
    $pub = new Http(T::HOST);
    $pub->get('/');
    T::contains(e($path), $pub->body, 'logo shown on the public site');
    $pub->get($path);
    T::eq(200, $pub->status, 'logo URL works');
    T::eq('image/png', $pub->headers['content-type'] ?? '', 'served as PNG');
    T::true(str_starts_with($pub->body, "\x89PNG"), 'real image bytes');
    $restore();
});

T::test('Invalid colours and non-image logo uploads are rejected', function () {
    $c = T::login(T::user('admin'));
    $c->get('/admin/branding');
    $c->post('/admin/branding', ['display_name' => 'X', 'primary_color' => 'red;}body{display:none', 'secondary_color' => '#000000']);
    $c->get('/admin/branding');
    T::contains('must be a colour', $c->body, 'bad colour refused');

    $fake = tempnam(sys_get_temp_dir(), 'logo');
    file_put_contents($fake, '<?php echo "pwned"; ?>');
    $c->post('/admin/branding', [
        'display_name' => 'X', 'primary_color' => '#000000', 'secondary_color' => '#000000',
        'logo' => new \CURLFile($fake, 'image/png', 'logo.png'),
    ]);
    $c->get('/admin/branding');
    T::contains('Only PNG, JPG or WEBP', $c->body, 'disguised PHP file refused');
    T::eq(null, DB::value('SELECT logo_path FROM tenant_settings LIMIT 1'), 'no logo stored');
    unlink($fake);
});

T::test('Database still rejects records that point at the wrong company (composite key)', function () {
    $u = T::user('homeowner');
    $failed = false;
    try {
        DB::run('INSERT INTO consents (tenant_id, user_id, consent_type, document_version, granted) VALUES (?,?,?,?,1)', [T::$companyId + 99, $u['id'], 'x', 'v1']);
    } catch (\PDOException $e) {
        $failed = true;
    }
    T::true($failed, 'mismatched company id rejected by the database');
});
