<div class="container">
  <div class="auth-card">
    <h1 class="h3 mb-3">Choose a new password</h1>
    <?php require BASE_PATH . '/app/Views/partials/flash.php'; ?>
    <?php if (!$valid): ?>
      <div class="alert alert-danger">This reset link is invalid or has expired.</div>
      <a href="/forgot-password" class="btn btn-brand">Request a new link</a>
    <?php else: ?>
      <form method="post" action="/reset-password" class="panel" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="u" value="<?= e($u) ?>"><input type="hidden" name="t" value="<?= e($t) ?>">
        <div class="mb-3"><label class="form-label" for="password">New password</label><input class="form-control" type="password" id="password" name="password" required minlength="10" autocomplete="new-password"><div class="form-text">At least 10 characters, with letters and a number.</div></div>
        <div class="mb-3"><label class="form-label" for="password_confirm">Confirm new password</label><input class="form-control" type="password" id="password_confirm" name="password_confirm" required autocomplete="new-password"></div>
        <button class="btn btn-brand w-100 py-2">Change password</button>
      </form>
    <?php endif; ?>
  </div>
</div>
