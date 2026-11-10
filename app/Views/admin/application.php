<?php
$O = App\Services\Ops::class;
$isProp = $kind === 'property';
$services = json_decode((string) $app['services_requested'], true) ?: [];
$st = $app['status'];
$closed = in_array($st, ['active', 'rejected', 'cancelled'], true);
?>
<a href="/admin/applications" class="small"><i class="bi bi-arrow-left"></i> Applications</a>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-2 mb-3">
  <div class="small text-body-secondary">Submitted <?= e($app['submitted_at'] ? date('j M Y H:i', strtotime($app['submitted_at'])) : '—') ?></div>
  <?= $O::badge($st) ?>
</div>
<div class="row g-4">
  <div class="col-xl-7">
    <div class="panel mb-3">
      <h2 class="h6">Customer</h2>
      <div><?= e($app['first_name'] . ' ' . $app['surname']) ?> · <a href="tel:<?= e($app['mobile']) ?>"><?= e($app['mobile']) ?></a> · <?= e($app['email']) ?></div>
      <h2 class="h6 mt-3"><?= $isProp ? 'Property' : 'Vehicle' ?> <?= $O::badge($asset['protection_status']) ?></h2>
      <?php if ($isProp): ?>
        <dl class="row small mb-0">
          <dt class="col-sm-4">Name / type</dt><dd class="col-sm-8"><?= e($asset['name'] . ' · ' . $asset['property_type']) ?></dd>
          <dt class="col-sm-4">Address</dt><dd class="col-sm-8"><?= e($asset['address'] . ', ' . $asset['suburb'] . ', ' . $asset['city']) ?><?= $asset['latitude'] ? ' <a target="_blank" rel="noopener" href="https://www.openstreetmap.org/?mlat=' . e($asset['latitude']) . '&mlon=' . e($asset['longitude']) . '#map=18/' . e($asset['latitude']) . '/' . e($asset['longitude']) . '">(map)</a>' : '' ?></dd>
          <dt class="col-sm-4">Existing security</dt><dd class="col-sm-8"><?= e($asset['existing_security'] ?: '—') ?></dd>
          <dt class="col-sm-4">Access</dt><dd class="col-sm-8"><?= e($asset['access_requirements'] ?: '—') ?></dd>
          <dt class="col-sm-4">Concerns</dt><dd class="col-sm-8"><?= e($asset['security_concerns'] ?: '—') ?></dd>
          <dt class="col-sm-4">Other</dt><dd class="col-sm-8"><?= e($asset['additional_info'] ?: '—') ?></dd>
        </dl>
      <?php else: ?>
        <dl class="row small mb-0">
          <dt class="col-sm-4">Vehicle</dt><dd class="col-sm-8"><?= e("{$asset['year']} {$asset['make']} {$asset['model']}, {$asset['colour']}") ?></dd>
          <dt class="col-sm-4">Registration / VIN</dt><dd class="col-sm-8"><?= e($asset['registration'] . ' / ' . ($asset['vin'] ?: '—')) ?></dd>
          <dt class="col-sm-4">Primary driver</dt><dd class="col-sm-8"><?= e($asset['primary_driver'] ?: '—') ?></dd>
          <dt class="col-sm-4">Other</dt><dd class="col-sm-8"><?= e($asset['additional_info'] ?: '—') ?></dd>
        </dl>
      <?php endif; ?>
      <h2 class="h6 mt-3">Services requested</h2>
      <div><?php foreach ($services as $s): ?><span class="feature-pill on"><?= e($s) ?></span><?php endforeach; ?></div>
      <?php if ($app['notes']): ?><p class="small mt-2 mb-0" style="white-space:pre-line">“<?= e($app['notes']) ?>”</p><?php endif; ?>
      <h2 class="h6 mt-3">Photos & documents</h2><?= App\Core\View::partial('shared/files', ['files' => $files]) ?>
    </div>
    <div class="panel"><h2 class="h6 mb-3">Timeline</h2><?= App\Core\View::partial('shared/timeline', ['timeline' => $timeline]) ?></div>
  </div>
  <div class="col-xl-5">
    <div class="panel mb-3">
      <h2 class="h6">Quotations</h2>
      <?php foreach ($quotes as $q): ?><div class="d-flex justify-content-between small border-bottom py-1"><a href="/admin/quotations/<?= (int) $q['id'] ?>"><?= e($q['reference']) ?></a><span><?= money_exact((int) $q['total_cents']) ?> <?= $O::badge($q['status']) ?></span></div><?php endforeach; ?>
      <?php if (!$closed): ?><a href="/admin/quotations/new/<?= $kind ?>/<?= (int) $app['id'] ?>" class="btn btn-sm btn-brand mt-2"><i class="bi bi-file-earmark-plus"></i> Prepare quotation</a><?php endif; ?>
    </div>
    <?php if ($st !== 'active' && $st !== 'cancelled'): ?>
      <form method="post" action="/admin/applications/<?= $kind ?>/<?= (int) $app['id'] ?>/status" class="panel mb-3">
        <?= csrf_field() ?>
        <h2 class="h6">Actions</h2>
        <textarea class="form-control form-control-sm mb-2" name="note" rows="2" placeholder="Message to the customer (optional; required for 'request info')"></textarea>
        <div class="d-flex flex-wrap gap-2">
          <?php if (in_array($st, ['submitted', 'assessment_required', 'rejected'], true)): ?><button name="action" value="review" class="btn btn-sm btn-outline-primary">Start review</button><?php endif; ?>
          <?php if (!$closed): ?>
            <button name="action" value="assessment" class="btn btn-sm btn-outline-warning">Assessment required</button>
            <button name="action" value="info" class="btn btn-sm btn-outline-secondary">Request info</button>
            <button name="action" value="reject" class="btn btn-sm btn-outline-danger" data-confirm-click="Reject this application?">Reject</button>
          <?php endif; ?>
          <?php if (in_array($st, ['awaiting_payment', 'awaiting_customer_acceptance', 'under_review', 'assessment_required', 'quotation_prepared'], true)): ?><button name="action" value="approve" class="btn btn-sm btn-outline-success">Approve (payment arranged)</button><?php endif; ?>
          <?php if ($st === 'approved'): ?><button name="action" value="activate" class="btn btn-sm btn-success">Activate protection</button><?php endif; ?>
          <button name="action" value="note" class="btn btn-sm btn-light border">Save internal note</button>
        </div>
        <p class="small text-body-secondary mt-2 mb-0">Activating marks the <?= $isProp ? 'property' : 'vehicle' ?> as protected, makes the customer a client and unlocks their panic button.</p>
      </form>
    <?php endif; ?>
  </div>
</div>
