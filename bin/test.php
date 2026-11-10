<?php
declare(strict_types=1);
/**
 * Automated test suite. Run inside the app container:
 *   docker compose exec app php bin/test.php           (all tests)
 *   docker compose exec app php bin/test.php Logins    (only files whose name contains "Logins")
 *
 * Uses a separate database (sentra360_test), rebuilt from scratch on every run,
 * and a private PHP web server on port 8099 pointed at that database.
 */
if (PHP_SAPI !== 'cli') {
    exit("CLI only\n");
}

$env = [
    'DB_NAME' => 'sentra360_test',
    'APP_ENV' => 'development',
    'APP_URL' => 'http://localhost:8099',
    'MAIL_PROVIDER' => '',
    'SMS_PROVIDER' => '',
    'COMPANY_NAME' => 'Test Security Co',
    'TRUST_PROXY' => '0',
];
foreach ($env as $k => $v) {
    putenv("$k=$v");
}
require dirname(__DIR__) . '/app/bootstrap.php';
require BASE_PATH . '/tests/Http.php';
require BASE_PATH . '/tests/TestCase.php';

use App\Core\DB;
use App\Services\Installer;
use Tests\T;

echo "Preparing test database...\n";
App\Core\DB::createDatabaseIfMissing();
Installer::dropAll();
Installer::migrate(fn() => null);
Installer::seedRoles(fn() => null);
T::$companyId = Installer::seedCompany(fn() => null);
\App\Services\Plans::seed(T::$companyId);

// Start a private web server for HTTP-level tests.
$serverEnv = array_merge(getenv(), $env);
$proc = proc_open(
    [PHP_BINARY, '-S', '127.0.0.1:8099', '-t', BASE_PATH . '/public', BASE_PATH . '/public/router.php'],
    [0 => ['pipe', 'r'], 1 => ['file', '/tmp/sentra-test-server.log', 'w'], 2 => ['file', '/tmp/sentra-test-server.log', 'a']],
    $pipes, BASE_PATH, $serverEnv
);
for ($i = 0; $i < 50 && !@fsockopen('127.0.0.1', 8099); $i++) {
    usleep(100000);
}

$filter = $argv[1] ?? '';
foreach (glob(BASE_PATH . '/tests/*Test.php') as $file) {
    if ($filter === '' || str_contains(basename($file), $filter)) {
        require $file;
    }
}

$pass = 0;
$fail = 0;
foreach (T::$tests as [$name, $fn]) {
    App\Core\Auth::reset();
    DB::run('DELETE FROM rate_limits'); // every test starts with fresh rate-limit counters
    try {
        $fn();
        $pass++;
        echo "  \033[32m✔\033[0m $name\n";
    } catch (\Throwable $e) {
        $fail++;
        echo "  \033[31m✘ $name\033[0m\n      " . $e->getMessage() . "\n";
        if (!$e instanceof Tests\AssertionFailed) {
            echo '      at ' . basename($e->getFile()) . ':' . $e->getLine() . "\n";
        }
    }
}

proc_terminate($proc);
echo "\n$pass passed, $fail failed\n";
if ($fail) {
    echo "Server log: /tmp/sentra-test-server.log\n";
}
exit($fail ? 1 : 0);
