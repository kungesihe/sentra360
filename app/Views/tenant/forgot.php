<?php $sent = !empty($_SESSION['forgot_sent']); unset($_SESSION['forgot_sent']); ?>
<div class="container">
  <div class="auth-card">
    <h1 class="h3 mb-3">Reset your password</h1>
    <?php require BASE_PATH . '/app/Views/partials/flash.php'; ?>
    <?php if ($sent): ?>
      <div class="alert alert-info">If an account exists for that email address, a reset link has been issued. The link is valid for 1 hour.</div>
      <?php if (!App\Core\Notifier::emailConfigured()): ?>
        <div class="not-configured mb-3"><strong>Email delivery is not configured on this server</strong>, so the link was not emailed. Please contact <?= e(tenant()['display_name']) ?> for help.<?php if (is_dev()): ?> Developers: see the <a href="/dev/outbox">developer outbox</a>.<?php endif; ?></div>
      <?php endif; ?>
    <?php endif; ?>
    <form method="post" action="/forgot-password" class="panel" novalidate>
      <?= csrf_field() ?>
      <div class="mb-3"><label class="form-label" for="email">Email address</label><input class="form-control" type="email" id="email" name="email" required autocomplete="email"></div>
      <button class="btn btn-brand w-100 py-2">Send reset link</button>
      <p class="text-center small mt-3 mb-0"><a href="/login">Back to sign in</a></p>
    </form>
  </div>
</div>
