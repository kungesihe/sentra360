<div class="container">
  <div class="auth-card">
    <h1 class="h3 mb-1">Welcome back</h1>
    <p class="text-body-secondary mb-4">Sign in with your email or mobile number.</p>
    <?php if (!empty($_GET['signed_out'])): ?><div class="alert alert-success">You have been signed out.</div><?php endif; ?>
    <?php require BASE_PATH . '/app/Views/partials/flash.php'; ?>
    <form method="post" action="/login" class="panel" novalidate>
      <?= csrf_field() ?>
      <div class="mb-3"><label class="form-label" for="identifier">Email or mobile number</label><input class="form-control form-control-lg" id="identifier" name="identifier" required autocomplete="username" value="<?= old('identifier') ?>"></div>
      <div class="mb-2"><label class="form-label" for="password">Password</label>
        <div class="input-group input-group-lg"><input class="form-control" type="password" id="password" name="password" required autocomplete="current-password"><button class="btn btn-outline-secondary" type="button" data-toggle-password="password" aria-label="Show password"><i class="bi bi-eye"></i></button></div>
      </div>
      <div class="text-end mb-3"><a href="/forgot-password" class="small">Forgot your password?</a></div>
      <button class="btn btn-brand btn-lg w-100">Sign in</button>
      <p class="text-center small mt-3 mb-0">New here? <a href="/register">Join your community</a></p>
    </form>
    <p class="text-center small text-body-secondary mt-3">Residents, company staff and security officers all sign in here.</p>
  </div>
</div>
