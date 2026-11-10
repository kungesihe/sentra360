<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\HttpException;
use App\Core\Router;
use App\Core\Session;
use App\Core\TenantContext;
use App\Core\View;

// ---- Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=(self)');
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; font-src 'self' https://cdn.jsdelivr.net https://fonts.gstatic.com; img-src 'self' data:; connect-src 'self'; frame-ancestors 'none'; form-action 'self'; base-uri 'self'");
if (is_https()) {
    header('Strict-Transport-Security: max-age=31536000');
}
// Behind a proxy, send plain-http visitors to https in production.
if (!is_https() && env('TRUST_PROXY') === '1' && config('env') === 'production' && ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'http') {
    header('Location: https://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '/'), true, 301);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

try {
    Session::start();
    if (!TenantContext::current()) {
        throw new HttpException(503, 'The company has not been set up yet. Run: php bin/install.php');
    }
    $router = new Router();
    require BASE_PATH . '/app/routes.php';
    $router->dispatch($method, $path);
} catch (HttpException $e) {
    http_response_code($e->status);
    View::render('errors/http', ['status' => $e->status, 'message' => $e->getMessage()], 'bare');
} catch (\Throwable $e) {
    error_log((string) $e);
    http_response_code(500);
    View::render('errors/http', [
        'status'  => 500,
        'message' => is_dev() ? get_class($e) . ': ' . $e->getMessage() . ' in ' . basename($e->getFile()) . ':' . $e->getLine() : 'Something went wrong on our side. Please try again.',
    ], 'bare');
}
