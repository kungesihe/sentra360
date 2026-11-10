<?php
/** Branded layout for a security company's public site and member area. */
$t = tenant();
$u = App\Core\Auth::user();
$safeColor = fn($c, $d) => preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $c) ? $c : $d;
$primary = $safeColor($t['primary_color'], '#0B3D91');
$secondary = $safeColor($t['secondary_color'], '#111827');
?>
<!doctype html>
<html lang="en">
<head>
<?php require BASE_PATH . '/app/Views/partials/head.php'; ?>
<title><?= e($title ?? '') ?> · <?= e($t['display_name']) ?></title>
<style>:root{--brand:<?= $primary ?>;--brand-2:<?= $secondary ?>;}</style>
</head>
<body class="tenant">
<?php if ($t['is_demo']): ?>
  <div class="demo-ribbon">Demonstration company — sample data only</div>
<?php endif; ?>
<nav class="navbar navbar-expand-lg tenant-nav" data-bs-theme="dark">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center gap-2" href="/">
      <?php if ($t['logo_path']): ?>
        <img src="<?= e($t['logo_path']) ?>" alt="" class="tenant-logo">
      <?php else: ?>
        <span class="tenant-monogram"><?= e(mb_strtoupper(mb_substr($t['display_name'], 0, 1))) ?></span>
      <?php endif; ?>
      <span class="fw-semibold"><?= e($t['display_name']) ?></span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#tNav" aria-controls="tNav" aria-expanded="false" aria-label="Menu"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="tNav">
      <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
        <?php if ($t['emergency_phone']): ?>
          <li class="nav-item"><a class="nav-link emergency-link" href="tel:<?= e(preg_replace('/[^\d+]/', '', $t['emergency_phone'])) ?>"><i class="bi bi-telephone-fill"></i> <?= e($t['emergency_phone']) ?></a></li>
        <?php endif; ?>
        <?php if ($u): ?>
          <?php if ($u['portal'] === 'homeowner'): $unreadH = App\Services\Ops::unreadCount((int) $u['id']); ?>
            <li class="nav-item"><a class="nav-link bell-link bell-dark" href="/account/notifications" aria-label="Notifications<?= $unreadH ? " ($unreadH unread)" : '' ?>"><i class="bi bi-bell"></i><?php if ($unreadH): ?><span class="bell-count"><?= $unreadH > 9 ? '9+' : $unreadH ?></span><?php endif; ?></a></li>
          <?php endif; ?>
          <li class="nav-item"><a class="nav-link" href="<?= e(App\Core\Auth::PORTALS[$u['portal']][2]) ?>"><?= $u['portal'] === 'homeowner' ? 'My account' : e(App\Core\Auth::PORTALS[$u['portal']][3]) . ' portal' ?></a></li>
          <li class="nav-item">
            <form method="post" action="/logout" class="d-inline"><?= csrf_field() ?><button class="btn btn-link nav-link">Sign out</button></form>
          </li>
        <?php else: ?>
          <li class="nav-item"><a class="nav-link" href="/login">Sign in</a></li>
          <li class="nav-item ms-lg-2"><a class="btn btn-brand-light btn-sm px-3" href="/register">Join as a homeowner</a></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>
<main class="tenant-main">
  <?= $content ?>
</main>
<footer class="tenant-footer">
  <div class="container py-4">
    <div class="row g-3 align-items-center">
      <div class="col-md">
        <div class="fw-semibold"><?= e($t['display_name']) ?></div>
        <div class="small text-body-secondary">
          <?php if ($t['contact_phone']): ?><i class="bi bi-telephone"></i> <?= e($t['contact_phone']) ?> &nbsp; <?php endif; ?>
          <?php if ($t['public_email']): ?><i class="bi bi-envelope"></i> <?= e($t['public_email']) ?><?php endif; ?>
        </div>
        <?php if ($t['psira_number'] && !str_starts_with((string) $t['psira_number'], 'DEMO')): ?><div class="small text-body-secondary">PSIRA reg. <?= e($t['psira_number']) ?></div><?php endif; ?>
      </div>
      <div class="col-md-auto small">
        <a href="/privacy" class="me-3">Privacy policy</a><a href="/terms" class="me-3">Terms</a><a href="/delete-account" class="me-3">Delete account</a>
      </div>
    </div>
    <div class="small text-body-secondary mt-3">Powered by <?= e(config('name')) ?></div>
  </div>
</footer>
<?php require BASE_PATH . '/app/Views/partials/scripts.php'; ?>
</body>
</html>
