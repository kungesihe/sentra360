<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

// ---- Load .env without overriding variables already set (e.g. by Docker or the test runner)
$envFile = BASE_PATH . '/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }
        [$k, $v] = array_map('trim', explode('=', $line, 2));
        if (strlen($v) >= 2 && ($v[0] === '"' || $v[0] === "'") && $v[-1] === $v[0]) {
            $v = substr($v, 1, -1);
        }
        if (getenv($k) === false) {
            putenv("$k=$v");
        }
    }
}

// ---- Autoloader: App\Foo\Bar -> app/Foo/Bar.php
spl_autoload_register(function (string $class): void {
    if (str_starts_with($class, 'App\\')) {
        $file = BASE_PATH . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

require BASE_PATH . '/app/helpers.php';

date_default_timezone_set('Africa/Johannesburg');

if (config('env') === 'development') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}
ini_set('log_errors', '1');
if (config('env') === 'development') {
    ini_set('error_log', BASE_PATH . '/storage/logs/php-error.log');
} // in production PHP logs to stderr, which hosting dashboards (e.g. Render "Logs") show
