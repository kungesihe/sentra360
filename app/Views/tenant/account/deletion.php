<?php $t = tenant(); ?>
<div class="container py-5" style="max-width:760px">
  <h1 class="h3 mb-3">Delete your account</h1>
  <?php require BASE_PATH . '/app/Views/partials/flash.php'; ?>
  <div class="panel mb-4">
    <h2 class="h6">What gets deleted</h2>
    <p class="small mb-2">When you ask us to delete your <?= e($t['display_name']) ?> account, we delete your profile (name, mobile number, email, password), your emergency contacts, registered properties and vehicles, photos you uploaded, and your sign-in history, within <strong>30 days</strong>.</p>
    <h2 class="h6">What we may keep</h2>
    <p class="small mb-0">Records we must keep by law or for safety, such as incident reports, payments and invoices, and audit logs, are kept only as long as required (typically up to 5 years for financial records) and are then deleted. These are not used for marketing.</p>
  </div>
  <?php if ($user && $user['portal'] === 'homeowner'): ?>
    <form method="post" action="/delete-account" class="panel" data-confirm="Send a request to delete your account?">
      <?= csrf_field() ?>
      <h2 class="h6">Request deletion</h2>
      <p class="small">Signed in as <strong><?= e($user['email']) ?></strong>.</p>
      <textarea class="form-control mb-2" name="reason" rows="2" maxlength="500" placeholder="Reason (optional)" aria-label="Reason"></textarea>
      <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="confirm" value="1" id="confirm"><label class="form-check-label small" for="confirm">I understand my account and personal information will be deleted.</label></div>
      <button class="btn btn-danger">Request account deletion</button>
    </form>
  <?php else: ?>
    <div class="panel">
      <h2 class="h6">How to request deletion</h2>
      <ol class="small mb-0">
        <li><a href="/login?next=delete">Sign in</a> to your account and open <strong>Profile &amp; security → Delete my account</strong>, or this page while signed in.</li>
        <li>Or contact us<?= $t['public_email'] ? ' at <strong>' . e($t['public_email']) . '</strong>' : '' ?><?= $t['contact_phone'] ? ' or on <strong>' . e($t['contact_phone']) . '</strong>' : '' ?> from the email or mobile number on your account.</li>
      </ol>
    </div>
  <?php endif; ?>
</div>
