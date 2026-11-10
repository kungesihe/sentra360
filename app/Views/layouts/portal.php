<?php
/** Layout for the two staff portals: Admin (/admin) and Security company (/security). */
$t = tenant();
$safeColor = fn($c, $d) => preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $c) ? $c : $d;
$p = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$portal = $portal ?? 'admin';
$navs = [
    'admin' => [
        ['/admin', 'speedometer2', 'Dashboard'],
        ['/admin/homeowners', 'house-door', 'Homeowners'],
        ['/admin/staff', 'person-badge', 'Admin & security logins'],
        ['/admin/branding', 'palette', 'Branding & profile'],
        ['/admin/audit', 'journal-text', 'Audit log'],
        ['/admin/profile', 'key', 'My login'],
    ],
    'security' => [
        ['/security', 'speedometer2', 'Dashboard'],
        ['/security/homeowners', 'house-door', 'Homeowners'],
        ['/security/profile', 'key', 'My login'],
    ],
];
$coming = [
    'admin' => ['Incidents', 'Property applications', 'Vehicle applications', 'Quotations', 'Services', 'Reports'],
    'security' => ['Incident feed', 'Panic alerts', 'My assignments', 'Map'],
];
$portalLabel = $portal === 'admin' ? 'Admin' : 'Security company';
?>
<!doctype html>
<html lang="en">
<head>
<?php require BASE_PATH . '/app/Views/partials/head.php'; ?>
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
        <a class="nav-link<?= ($p === $href || ($href !== $home && str_starts_with($p, $href))) ? ' active' : '' ?>" href="<?= $href ?>"><i class="bi bi-<?= $icon ?>"></i> <?= e($label) ?></a>
      <?php endforeach; ?>
      <div class="side-label">Coming in later phases</div>
      <?php foreach ($coming[$portal] as $c): ?><span class="nav-link disabled"><i class="bi bi-lock"></i> <?= e($c) ?></span><?php endforeach; ?>
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
      <h1 class="h4 mb-4"><?= e($title ?? '') ?></h1>
      <?php require BASE_PATH . '/app/Views/partials/flash.php'; ?>
      <?= $content ?>
    </div>
  </div>
</div>
<?php require BASE_PATH . '/app/Views/partials/scripts.php'; ?>
</body>
</html>
