<div class="row g-4">
  <div class="col-lg-5">
    <div class="panel">
      <h2 class="h6 mb-3">Your details</h2>
      <dl class="small mb-0">
        <dt>Name</dt><dd><?= e($me['first_name'] . ' ' . $me['surname']) ?></dd>
        <dt>Login type</dt><dd><?= e($me['role_name']) ?></dd>
        <dt>Email</dt><dd class="text-break"><?= e($me['email']) ?></dd>
        <dt>Mobile</dt><dd class="mb-0"><?= e($me['mobile']) ?></dd>
      </dl>
    </div>
  </div>
  <div class="col-lg-7">
    <form method="post" action="<?= e($action) ?>" class="panel" novalidate>
      <?= csrf_field() ?>
      <h2 class="h6 mb-3">Change password</h2>
      <div class="mb-3"><label class="form-label" for="current_password">Current password</label><input class="form-control" type="password" id="current_password" name="current_password" required autocomplete="current-password"></div>
      <div class="mb-3"><label class="form-label" for="password">New password</label><input class="form-control" type="password" id="password" name="password" required minlength="10" autocomplete="new-password"><div class="form-text">At least 10 characters, with letters and a number.</div></div>
      <div class="mb-3"><label class="form-label" for="password_confirm">Confirm new password</label><input class="form-control" type="password" id="password_confirm" name="password_confirm" required autocomplete="new-password"></div>
      <button class="btn btn-brand">Change password</button>
    </form>
  </div>
</div>
