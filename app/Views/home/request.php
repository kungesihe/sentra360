<div class="container py-4" style="max-width:1000px">
  <?= App\Core\View::partial('home/_nav', ['active' => '/account/requests']) ?>
  <?php require BASE_PATH . '/app/Views/partials/flash.php'; ?>
  <a href="/account/requests" class="small"><i class="bi bi-arrow-left"></i> My bookings</a>
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mt-2 mb-3">
    <div><h1 class="h4 mb-0"><?= e($r['service_name']) ?></h1><div class="text-body-secondary">Request <strong><?= e($r['reference']) ?></strong></div></div>
    <?= App\Services\Ops::badge($r['status']) ?>
  </div>
  <?= App\Core\View::partial('shared/progress', ['steps' => ['submitted', 'under_review', 'awaiting_customer_acceptance', 'awaiting_payment', 'confirmed', 'completed'], 'current' => $r['status']]) ?>
  <div class="row g-4">
    <div class="col-lg-7">
      <?php foreach ($quotes as $q): ?>
        <a href="/account/quotations/<?= (int) $q['id'] ?>" class="alert <?= $q['status'] === 'sent' ? 'alert-warning' : 'alert-light border' ?> d-flex justify-content-between text-decoration-none"><span>Quotation <strong><?= e($q['reference']) ?></strong> <?= App\Services\Ops::badge($q['status']) ?></span><strong><?= money_exact((int) $q['total_cents']) ?></strong></a>
      <?php endforeach; ?>
      <div class="panel mb-3">
        <dl class="row small mb-0">
          <dt class="col-sm-4">Date & time</dt><dd class="col-sm-8"><?= e($r['requested_date'] . ($r['requested_time'] ? ' at ' . substr($r['requested_time'], 0, 5) : '')) ?></dd>
          <dt class="col-sm-4">Location</dt><dd class="col-sm-8"><?= e($r['location_text']) ?></dd>
          <dt class="col-sm-4">Duration</dt><dd class="col-sm-8"><?= e($r['duration_text'] ?: '—') ?></dd>
          <dt class="col-sm-4">Instructions</dt><dd class="col-sm-8" style="white-space:pre-line"><?= e($r['instructions'] ?: '—') ?></dd>
        </dl>
      </div>
      <?php if (!in_array($r['status'], ['completed', 'cancelled', 'rejected'], true)): ?>
        <form method="post" action="/account/requests/<?= (int) $r['id'] ?>/cancel" data-confirm="Cancel this request?"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger">Cancel request</button></form>
      <?php endif; ?>
    </div>
    <div class="col-lg-5"><div class="panel"><h2 class="h6 mb-3">Progress</h2><?= App\Core\View::partial('shared/timeline', ['timeline' => $timeline]) ?></div></div>
  </div>
</div>
