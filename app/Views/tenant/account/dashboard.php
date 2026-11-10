<?php $verified = $user['email_verified_at'] && $user['mobile_verified_at']; ?>
<div class="container py-5">
  <?php require BASE_PATH . '/app/Views/partials/flash.php'; ?>
  <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
    <div>
      <div class="text-body-secondary small">My account</div>
      <h1 class="h3 mb-0">Hello, <?= e($user['first_name']) ?></h1>
    </div>
    <div class="d-flex gap-2 align-items-center">
      <?= status_badge($user['client_status']) ?>
      <a href="/account/profile" class="btn btn-outline-secondary btn-sm"><i class="bi bi-person-gear"></i> Profile & security</a>
    </div>
  </div>

  <?php if (!$verified): ?>
    <div class="alert alert-warning d-flex flex-wrap align-items-center gap-3">
      <i class="bi bi-shield-exclamation fs-4"></i>
      <div class="flex-grow-1"><strong>Verify your account.</strong> Confirm your mobile number and email so <?= e(tenant()['display_name']) ?> can trust your reports and contact you.</div>
      <a href="/account/verify" class="btn btn-sm btn-dark">Verify now</a>
    </div>
  <?php endif; ?>

  <div class="row g-3 mb-4">
    <?php foreach ([['Incident reports', 'megaphone'], ['Applications', 'house-check'], ['Quotations', 'file-earmark-text'], ['Bookings', 'calendar-check']] as [$l, $i]): ?>
      <div class="col-6 col-lg-3"><div class="stat"><div class="label"><i class="bi bi-<?= $i ?>"></i> <?= $l ?></div><div class="value">0</div></div></div>
    <?php endforeach; ?>
  </div>

  <div class="row g-4">
    <div class="col-lg-8">
      <div class="panel h-100">
        <h2 class="h5">What you can do</h2>
        <p class="text-body-secondary">Online incident reporting and protection applications are being switched on for <?= e(tenant()['display_name']) ?>. You'll find them here as soon as they're available.</p>
        <ul class="check-list mb-0">
          <li>Report crime, suspicious activity and accidents</li>
          <li>Apply to protect your home or vehicle and receive a quotation</li>
          <li>Book specialised security services</li>
        </ul>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="panel h-100">
        <h2 class="h5">Your details</h2>
        <dl class="small mb-0">
          <dt>Mobile</dt><dd><?= e($user['mobile']) ?> <?= $user['mobile_verified_at'] ? '<i class="bi bi-patch-check-fill text-success" title="Verified"></i>' : '<span class="badge text-bg-warning">Not verified</span>' ?></dd>
          <dt>Email</dt><dd><?= e($user['email']) ?> <?= $user['email_verified_at'] ? '<i class="bi bi-patch-check-fill text-success" title="Verified"></i>' : '<span class="badge text-bg-warning">Not verified</span>' ?></dd>
          <dt>Member since</dt><dd class="mb-0"><?= e(date('j M Y', strtotime($user['created_at']))) ?></dd>
        </dl>
      </div>
    </div>
  </div>
  <?php if (!$user['is_staff']): ?>
    <div class="panel mt-4 d-flex align-items-center gap-3">
      <i class="bi bi-broadcast-pin fs-3 text-secondary"></i>
      <div class="small text-body-secondary">The panic button is only available to verified clients with an active protection service. Ask <?= e(tenant()['display_name']) ?> about protection packages.</div>
    </div>
  <?php endif; ?>
</div>
