<?php
$smsCfg = App\Core\Notifier::smsConfigured();
$mailCfg = App\Core\Notifier::emailConfigured();
$deliveryText = fn($status, $what) => match ($status) {
    'sent' => "We sent $what.",
    'failed' => "We could not send $what. Please try again later or contact us.",
    default => null,
};
?>
<div class="container">
  <div class="auth-card wide">
    <h1 class="h3 mb-1">Verify your account</h1>
    <p class="text-body-secondary mb-4">Both steps are needed before your account is fully active.</p>
    <?php require BASE_PATH . '/app/Views/partials/flash.php'; ?>

    <?php if (!$smsCfg || !$mailCfg): ?>
      <div class="not-configured mb-4">
        <strong>Message delivery is not set up on this server.</strong>
        <?= !$smsCfg ? 'SMS codes are <u>not</u> being sent.' : '' ?> <?= !$mailCfg ? 'Emails are <u>not</u> being sent.' : '' ?>
        <?php if (is_dev()): ?>Developers can read the undelivered messages in the <a href="/dev/outbox" target="_blank">developer outbox</a>.<?php else: ?>Please contact <?= e(tenant()['display_name']) ?> to verify your account.<?php endif; ?>
      </div>
    <?php endif; ?>

    <div class="panel mb-3">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h2 class="h5 mb-0">1. Mobile number</h2>
        <?= $user['mobile_verified_at'] ? '<span class="badge text-bg-success">Verified</span>' : '<span class="badge text-bg-warning">Pending</span>' ?>
      </div>
      <?php if (!$user['mobile_verified_at']): ?>
        <?php if ($m = $deliveryText($delivery['sms'] ?? null, 'a 6-digit code to ' . mask_mobile($user['mobile']))): ?><p class="small"><?= e($m) ?></p><?php endif; ?>
        <form method="post" action="/account/verify/mobile" class="row g-2 align-items-end">
          <?= csrf_field() ?>
          <div class="col-sm-7"><label class="form-label" for="code">Verification code for <?= e(mask_mobile($user['mobile'])) ?></label><input class="form-control otp-input" id="code" name="code" inputmode="numeric" pattern="\d{6}" maxlength="6" autocomplete="one-time-code" required></div>
          <div class="col-sm-5"><button class="btn btn-brand w-100 py-2">Verify</button></div>
        </form>
        <form method="post" action="/account/verify/resend-sms" class="mt-2"><?= csrf_field() ?><button class="btn btn-link btn-sm p-0">Request a new code</button></form>
      <?php else: ?>
        <p class="small mb-0 text-body-secondary"><?= e($user['mobile']) ?> is verified.</p>
      <?php endif; ?>
    </div>

    <div class="panel mb-4">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h2 class="h5 mb-0">2. Email address</h2>
        <?= $user['email_verified_at'] ? '<span class="badge text-bg-success">Verified</span>' : '<span class="badge text-bg-warning">Pending</span>' ?>
      </div>
      <?php if (!$user['email_verified_at']): ?>
        <p class="small mb-2">Open the confirmation link sent to <?= e(mask_email($user['email'])) ?>. <?php if ($m = $deliveryText($delivery['email'] ?? null, 'the link')): ?><?= e($m) ?><?php endif; ?></p>
        <form method="post" action="/account/verify/resend-email"><?= csrf_field() ?><button class="btn btn-outline-secondary btn-sm">Send a new link</button></form>
      <?php else: ?>
        <p class="small mb-0 text-body-secondary"><?= e($user['email']) ?> is verified.</p>
      <?php endif; ?>
    </div>
    <a href="/account" class="btn btn-link px-0">Continue to my account <i class="bi bi-arrow-right"></i></a>
  </div>
</div>
