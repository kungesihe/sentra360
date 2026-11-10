<?php
$tabs = [
    'homeowner' => ['/login', 'house-door', 'Homeowner'],
    'admin'     => ['/admin/login', 'building', 'Admin'],
    'security'  => ['/security/login', 'shield-shaded', 'Security company'],
];
$intro = [
    'homeowner' => 'Residents and clients of ' . tenant()['display_name'],
    'admin'     => 'Company office staff',
    'security'  => 'Control room and response officers',
];
?>
<div class="container">
  <div class="auth-card">
    <h1 class="h3 mb-3">Sign in</h1>
    <nav class="nav nav-pills nav-fill login-tabs mb-3" aria-label="Choose your login">
      <?php foreach ($tabs as $key => [$href, $icon, $label]): ?>
        <a class="nav-link<?= $portal === $key ? ' active' : '' ?>" href="<?= $href ?>"<?= $portal === $key ? ' aria-current="page"' : '' ?>><i class="bi bi-<?= $icon ?>"></i> <?= e($label) ?></a>
      <?php endforeach; ?>
    </nav>
    <p class="text-body-secondary small mb-3"><?= e($intro[$portal]) ?></p>
    <?php if (!empty($_GET['signed_out'])): ?><div class="alert alert-success">You have been signed out.</div><?php endif; ?>
    <?php require BASE_PATH . '/app/Views/partials/flash.php'; ?>
    <form method="post" action="<?= e($action) ?>" class="panel" novalidate>
      <?= csrf_field() ?>
      <div class="mb-3"><label class="form-label" for="identifier">Email or mobile number</label><input class="form-control" id="identifier" name="identifier" required autocomplete="username" value="<?= old('identifier') ?>"></div>
      <div class="mb-2"><label class="form-label" for="password">Password</label>
        <div class="input-group"><input class="form-control" type="password" id="password" name="password" required autocomplete="current-password"><button class="btn btn-outline-secondary" type="button" data-toggle-password="password" aria-label="Show password"><i class="bi bi-eye"></i></button></div>
      </div>
      <div class="text-end mb-3"><a href="/forgot-password" class="small">Forgot your password?</a></div>
      <button class="btn btn-brand w-100 py-2">Sign in as <?= e(strtolower($tabs[$portal][2])) ?></button>
      <?php if ($portal === 'homeowner'): ?><p class="text-center small mt-3 mb-0">New here? <a href="/register">Create a homeowner account</a></p><?php else: ?><p class="text-center small text-body-secondary mt-3 mb-0">Staff logins are created by the company admin.</p><?php endif; ?>
    </form>
  </div>
</div>
