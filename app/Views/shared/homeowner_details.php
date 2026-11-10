<?php /** Homeowner details panel, shared by both staff portals. Needs $m. */ ?>
<div class="panel">
  <div class="d-flex justify-content-between align-items-start mb-3">
    <h2 class="h5 mb-0">Homeowner details</h2>
    <div class="d-flex gap-1"><?= status_badge($m['status']) ?> <?= status_badge($m['client_status']) ?></div>
  </div>
  <dl class="row mb-0">
    <dt class="col-sm-4">Name</dt><dd class="col-sm-8"><?= e($m['first_name'] . ' ' . $m['surname']) ?></dd>
    <dt class="col-sm-4">Mobile</dt><dd class="col-sm-8"><a href="tel:<?= e($m['mobile']) ?>"><?= e($m['mobile']) ?></a> <?= $m['mobile_verified_at'] ? '<span class="badge text-bg-success">verified</span>' : '<span class="badge text-bg-warning">not verified</span>' ?></dd>
    <dt class="col-sm-4">Email</dt><dd class="col-sm-8 text-break"><?= e($m['email']) ?> <?= $m['email_verified_at'] ? '<span class="badge text-bg-success">verified</span>' : '<span class="badge text-bg-warning">not verified</span>' ?></dd>
    <dt class="col-sm-4">Joined</dt><dd class="col-sm-8"><?= e($m['created_at']) ?></dd>
    <dt class="col-sm-4">Last sign-in</dt><dd class="col-sm-8"><?= e($m['last_login_at'] ?? '—') ?></dd>
    <dt class="col-sm-4">Consents</dt>
    <dd class="col-sm-8 mb-0">
      <?php foreach ($m['consents'] as $c): ?><div class="small"><?= e($c['consent_type']) ?>: <?= $c['granted'] ? 'given' : 'withdrawn' ?> (v<?= e($c['document_version']) ?>, <?= e(date('j M Y', strtotime($c['created_at']))) ?>)</div><?php endforeach; ?>
      <?php if (!$m['consents']): ?><span class="text-body-secondary small">None recorded</span><?php endif; ?>
    </dd>
  </dl>
</div>
<p class="small text-body-secondary mt-3"><i class="bi bi-journal-check"></i> Your access to this record has been logged.</p>
