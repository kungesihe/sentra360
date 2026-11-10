<?php
/** Layout for the two staff portals: Admin (/admin) and Security company (/security). */
$t = tenant();
$safeColor = fn($c, $d) => preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $c) ? $c : $d;
$p = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$portal = $portal ?? 'admin';
$navs = [
    'admin' => [
        ['/admin', 'speedometer2', 'Dashboard'],
        ['/admin/incidents', 'megaphone', 'Incidents'],
        ['/admin/applications', 'house-check', 'Protection applications'],
        ['/admin/quotations', 'file-earmark-text', 'Quotations'],
        ['/admin/requests', 'calendar-check', 'Service requests'],
        ['/admin/services', 'bag-check', 'Services'],
        ['/admin/payments', 'cash-coin', 'Payments'],
        ['/admin/homeowners', 'house-door', 'Homeowners'],
        ['/admin/alerts', 'broadcast', 'Safety alerts'],
        ['/admin/patrols', 'signpost-split', 'Patrol log'],
        ['/admin/reports', 'bar-chart', 'Reports'],
        ['/admin/staff', 'person-badge', 'Admin & security logins'],
        ['/admin/branding', 'palette', 'Branding & profile'],
        ['/admin/audit', 'journal-text', 'Audit log'],
        ['/admin/notifications', 'bell', 'Notifications'],
        ['/admin/profile', 'key', 'My login'],
    ],
    'security' => [
        ['/security', 'display', 'Control room'],
        ['/security/assignments', 'person-walking', 'My assignments'],
        ['/security/incidents', 'megaphone', 'Incidents'],
        ['/security/patrols', 'signpost-split', 'Patrol log'],
        ['/security/homeowners', 'house-door', 'Homeowners'],
        ['/security/notifications', 'bell', 'Notifications'],
        ['/security/profile', 'key', 'My login'],
    ],
];
$unread = App\Services\Ops::unreadCount((int) $me['id']);
$portalLabel = $portal === 'admin' ? 'Admin' : 'Security company';
?>
<!doctype html>
<html lang="en">
<head>
<?php require BASE_PATH . '/app/Views/partials/head.php'; ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="anonymous">
<title><?= e($title ?? '') ?> · <?= e($t['display_name']) ?> <?= e($portalLabel) ?></title>
<style>:root{--brand:<?= $safeColor($t['primary_color'], '#0B3D91') ?>;--brand-2:<?= $safeColor($t['secondary_color'], '#111827') ?>;}</style>
</head>
<body class="portal portal-<?= e($portal) ?>">
<div class="portal-shell">
  <aside class="portal-side offcanvas-lg offcanvas-start" tabindex="-1" id="side">
    <div class="p-3 d-flex align-items-center gap-2 border-bottom border-light border-opacity-10">
      <?php if ($t['logo_path']): ?><img src="<?= e($t['logo_path']) ?>" alt="" class="tenant-logo"><?php else: ?><span class="tenant-monogram"><?= e(mb_strtoupper(mb_substr($t['display_name'], 0, 1))) ?></span><?php endif; ?>
      <div class="lh-sm"><div class="fw-semibold text-white small"><?= e($t['display_name']) ?></div><div class="text-white-50" style="font-size:.75rem"><?= e($portalLabel) ?></div></div>
      <button type="button" class="btn-close btn-close-white ms-auto d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#side" aria-label="Close"></button>
    </div>
    <nav class="nav flex-column p-2">
      <?php $home = $navs[$portal][0][0]; foreach ($navs[$portal] as [$href, $icon, $label]): ?>
        <a class="nav-link<?= ($p === $href || ($href !== $home && str_starts_with($p, $href))) ? ' active' : '' ?>" href="<?= $href ?>"><i class="bi bi-<?= $icon ?>"></i> <?= e($label) ?><?php if (str_ends_with($href, '/notifications') && $unread): ?> <span class="badge rounded-pill text-bg-danger ms-auto"><?= $unread ?></span><?php endif; ?></a>
      <?php endforeach; ?>
    </nav>
  </aside>
  <div class="portal-main">
    <header class="portal-top">
      <button class="btn btn-sm btn-outline-secondary d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#side" aria-controls="side" aria-label="Menu"><i class="bi bi-list"></i></button>
      <div class="ms-auto d-flex align-items-center gap-3">
        <a href="/" class="small d-none d-sm-inline" target="_blank" rel="noopener">View public site <i class="bi bi-box-arrow-up-right"></i></a>
        <span class="small text-body-secondary d-none d-md-inline"><?= e($me['first_name'] . ' ' . $me['surname']) ?></span>
        <form method="post" action="/logout"><?= csrf_field() ?><button class="btn btn-sm btn-outline-secondary">Sign out</button></form>
      </div>
    </header>
    <div class="portal-content">
      <h1 class="h4 mb-4 no-print-title"><?= e($title ?? '') ?></h1>
      <?php require BASE_PATH . '/app/Views/partials/flash.php'; ?>
      <?= $content ?>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin="anonymous" async id="leaflet-js"></script>
<?php require BASE_PATH . '/app/Views/partials/scripts.php'; ?>
</body>
</html>
