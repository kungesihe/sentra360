<?php $isProp = $kind === 'property'; $services = json_decode((string) $app['services_requested'], true) ?: []; ?>
<div class="container py-4" style="max-width:1000px">
  <?= App\Core\View::partial('home/_nav', ['active' => '/account/applications']) ?>
  <?php require BASE_PATH . '/app/Views/partials/flash.php'; ?>
  <a href="/account/applications" class="small"><i class="bi bi-arrow-left"></i> My applications</a>
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mt-2 mb-3">
    <div><h1 class="h4 mb-0"><?= e(App\Services\Workflow::KINDS[$kind][4]) ?></h1><div class="text-body-secondary">Application <strong><?= e($app['reference']) ?></strong></div></div>
    <?= App\Services\Ops::badge($app['status']) ?>
  </div>
  <?= App\Core\View::partial('shared/progress', ['steps' => ['submitted', 'under_review', 'awaiting_customer_acceptance', 'awaiting_payment', 'approved', 'active'], 'current' => in_array($app['status'], ['assessment_required', 'quotation_prepared', 'quotation_sent'], true) ? 'under_review' : $app['status']]) ?>
  <div class="row g-4">
    <div class="col-lg-7">
      <?php foreach ($quotes as $q): ?>
        <a href="/account/quotations/<?= (int) $q['id'] ?>" class="alert <?= $q['status'] === 'sent' ? 'alert-warning' : 'alert-light border' ?> d-flex justify-content-between align-items-center text-decoration-none">
          <span><i class="bi bi-file-earmark-text"></i> Quotation <strong><?= e($q['reference']) ?></strong> · <?= App\Services\Ops::badge($q['status']) ?><?= $q['status'] === 'sent' ? ' <strong>Review and respond</strong>' : '' ?></span>
          <strong><?= money_exact((int) $q['total_cents']) ?><?= $q['billing'] === 'monthly' ? '/month' : '' ?></strong>
        </a>
      <?php endforeach; ?>
      <div class="panel mb-4">
        <h2 class="h6"><?= $isProp ? 'Property' : 'Vehicle' ?></h2>
        <?php if ($isProp): ?>
          <div class="fw-semibold"><?= e($asset['name']) ?> <span class="text-body-secondary small">(<?= e($asset['property_type']) ?>)</span></div>
          <div class="small"><?= e($asset['address'] . ', ' . $asset['suburb'] . ', ' . $asset['city']) ?></div>
        <?php else: ?>
          <div class="fw-semibold"><?= e($asset['make'] . ' ' . $asset['model']) ?> <?= $asset['year'] ? e('(' . $asset['year'] . ')') : '' ?></div>
          <div class="small"><?= e($asset['colour'] . ' · ' . $asset['registration']) ?></div>
        <?php endif; ?>
        <div class="small mt-2">Protection: <?= App\Services\Ops::badge($asset['protection_status']) ?></div>
        <h2 class="h6 mt-3">Services requested</h2>
        <div><?php foreach ($services as $s): ?><span class="feature-pill on"><?= e($s) ?></span><?php endforeach; ?></div>
        <?php if ($app['notes']): ?><p class="small mt-2 mb-0" style="white-space:pre-line"><?= e($app['notes']) ?></p><?php endif; ?>
      </div>
      <?php if (!in_array($app['status'], ['active', 'cancelled', 'rejected'], true)): ?>
        <form method="post" action="/account/applications/<?= $kind ?>/<?= (int) $app['id'] ?>/note" enctype="multipart/form-data" class="panel mb-3">
          <?= csrf_field() ?>
          <h2 class="h6">Message the company / send more information</h2>
          <textarea class="form-control mb-2" name="note" rows="2" maxlength="2000"></textarea>
          <input class="form-control mb-2" type="file" name="files[]" multiple accept="image/png,image/jpeg,image/webp,application/pdf">
          <button class="btn btn-sm btn-brand">Send</button>
        </form>
        <form method="post" action="/account/applications/<?= $kind ?>/<?= (int) $app['id'] ?>/cancel" data-confirm="Cancel this application?"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger">Cancel application</button></form>
      <?php endif; ?>
    </div>
    <div class="col-lg-5"><div class="panel"><h2 class="h6 mb-3">Progress</h2><?= App\Core\View::partial('shared/timeline', ['timeline' => $timeline]) ?></div></div>
  </div>
</div>
