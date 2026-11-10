<?php $O = App\Services\Ops::class; $closed = in_array($r['status'], ['completed', 'cancelled', 'rejected'], true); ?>
<a href="/admin/requests" class="small"><i class="bi bi-arrow-left"></i> Service requests</a>
<div class="d-flex justify-content-between align-items-center mt-2 mb-3"><div class="h5 mb-0"><?= e($r['service_name']) ?></div><?= $O::badge($r['status']) ?></div>
<div class="row g-4">
  <div class="col-xl-7">
    <div class="panel mb-3">
      <dl class="row small mb-0">
        <dt class="col-sm-4">Customer</dt><dd class="col-sm-8"><?= e($r['first_name'] . ' ' . $r['surname']) ?> · <a href="tel:<?= e($r['mobile']) ?>"><?= e($r['mobile']) ?></a> · <?= e($r['email']) ?></dd>
        <dt class="col-sm-4">Date & time</dt><dd class="col-sm-8"><?= e($r['requested_date'] . ($r['requested_time'] ? ' at ' . substr($r['requested_time'], 0, 5) : '')) ?></dd>
        <dt class="col-sm-4">Location</dt><dd class="col-sm-8"><?= e($r['location_text']) ?></dd>
        <dt class="col-sm-4">Duration</dt><dd class="col-sm-8"><?= e($r['duration_text'] ?: '—') ?></dd>
        <dt class="col-sm-4">Instructions</dt><dd class="col-sm-8" style="white-space:pre-line"><?= e($r['instructions'] ?: '—') ?></dd>
        <dt class="col-sm-4">Listed price</dt><dd class="col-sm-8"><?= App\Core\View::partial('shared/price', ['s' => $r]) ?></dd>
      </dl>
    </div>
    <div class="panel"><h2 class="h6 mb-3">Timeline</h2><?= App\Core\View::partial('shared/timeline', ['timeline' => $timeline]) ?></div>
  </div>
  <div class="col-xl-5">
    <div class="panel mb-3">
      <h2 class="h6">Quotations</h2>
      <?php foreach ($quotes as $q): ?><div class="d-flex justify-content-between small border-bottom py-1"><a href="/admin/quotations/<?= (int) $q['id'] ?>"><?= e($q['reference']) ?></a><span><?= money_exact((int) $q['total_cents']) ?> <?= $O::badge($q['status']) ?></span></div><?php endforeach; ?>
      <?php if (!$closed): ?><a href="/admin/quotations/new/service/<?= (int) $r['id'] ?>" class="btn btn-sm btn-brand mt-2">Prepare quotation / payment request</a><?php endif; ?>
    </div>
    <?php if (!$closed): ?>
      <form method="post" action="/admin/requests/<?= (int) $r['id'] ?>/status" class="panel">
        <?= csrf_field() ?>
        <h2 class="h6">Actions</h2>
        <textarea class="form-control form-control-sm mb-2" name="note" rows="2" placeholder="Message to the customer (optional)"></textarea>
        <div class="d-flex flex-wrap gap-2">
          <button name="action" value="review" class="btn btn-sm btn-outline-primary">Under review</button>
          <button name="action" value="confirm" class="btn btn-sm btn-success">Confirm booking</button>
          <?php if ($r['status'] === 'confirmed'): ?><button name="action" value="complete" class="btn btn-sm btn-dark">Mark completed</button><?php endif; ?>
          <button name="action" value="reject" class="btn btn-sm btn-outline-danger" data-confirm-click="Decline this request?">Decline</button>
        </div>
      </form>
    <?php endif; ?>
  </div>
</div>
