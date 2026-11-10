<?php
declare(strict_types=1);
/**
 * SENTRA 360 installer.
 *
 *   php bin/install.php            run migrations, create roles, the company and the first admin (from .env)
 *   php bin/install.php --demo     ...and load demonstration accounts (not allowed in production)
 *   php bin/install.php --fresh    DROP ALL TABLES first (development only)
 *   php bin/install.php --create-db   create the DB_NAME database first if it doesn't exist (hosted MySQL/TiDB)
 */
if (PHP_SAPI !== 'cli') {
    exit("CLI only\n");
}
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\DemoSeeder;
use App\Services\Installer;

$args = array_slice($argv, 1);
$out = fn(string $s) => print($s . PHP_EOL);

if (in_array('--create-db', $args, true)) {
    App\Core\DB::createDatabaseIfMissing();
}

if (in_array('--fresh', $args, true)) {
    if (config('env') === 'production') {
        exit("Refusing --fresh in production.\n");
    }
    $out('Dropping all tables...');
    Installer::dropAll();
}

$out('Running migrations...');
Installer::migrate($out);
$out('Setting up...');
Installer::seedRoles($out);
$tenantId = Installer::seedCompany($out);
Installer::seedServices($tenantId, $out);

if (in_array('--demo', $args, true)) {
    $out('Loading demonstration accounts...');
    DemoSeeder::run($tenantId, $out);
} else {
    Installer::seedAdmin($tenantId, $out);
}
$out('Done. Open ' . config('url'));
