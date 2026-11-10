<?php $isProp = $kind === 'property'; ?>
<div class="container py-4">
  <?= App\Core\View::partial('home/_nav', []) ?>
  <?php require BASE_PATH . '/app/Views/partials/flash.php'; ?>
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><?= e($title) ?></h1>
    <a href="/account/assets/<?= $kind ?>/new" class="btn btn-brand"><i class="bi bi-plus-lg"></i> <?= $isProp ? 'Register a property' : 'Register a vehicle' ?></a>
  </div>
  <div class="row g-3">
    <?php foreach ($assets as $a): ?>
      <div class="col-md-6 col-xl-4">
        <div class="panel h-100">
          <div class="d-flex justify-content-between align-items-start mb-2">
            <div class="fw-semibold"><?= $isProp ? e($a['name']) : e($a['make'] . ' ' . $a['model']) ?></div>
            <?= App\Services\Ops::badge($a['protection_status']) ?>
          </div>
          <div class="small text-body-secondary mb-3"><?= $isProp ? e($a['address'] . ', ' . $a['suburb'] . ', ' . $a['city']) : e($a['colour'] . ' · ' . $a['registration'] . ($a['year'] ? ' · ' . $a['year'] : '')) ?></div>
          <?php if ($a['app_id']): ?>
            <div class="small mb-2">Latest application: <a href="/account/applications/<?= $kind ?>/<?= (int) $a['app_id'] ?>"><?= App\Services\Ops::badge($a['app_status']) ?></a></div>
          <?php endif; ?>
          <?php if ($a['protection_status'] !== 'active' && (!$a['app_status'] || in_array($a['app_status'], ['rejected', 'cancelled'], true))): ?>
            <details>
              <summary class="btn btn-sm btn-outline-dark">Apply for protection</summary>
              <form method="post" action="/account/assets/<?= $kind ?>/<?= (int) $a['id'] ?>/apply" class="mt-2">
                <?= csrf_field() ?>
                <?php foreach (($isProp ? App\Controllers\Tenant\HomeAssetsController::PROPERTY_SERVICES : App\Controllers\Tenant\HomeAssetsController::VEHICLE_SERVICES) as $i => $s): ?>
                  <div class="form-check small"><input class="form-check-input" type="checkbox" name="services[]" value="<?= e($s) ?>" id="s<?= $a['id'] . '-' . $i ?>"><label class="form-check-label" for="s<?= $a['id'] . '-' . $i ?>"><?= e($s) ?></label></div>
                <?php endforeach; ?>
                <textarea class="form-control form-control-sm my-2" name="notes" rows="2" placeholder="Anything we should know (optional)"></textarea>
                <button class="btn btn-sm btn-brand">Submit application</button>
              </form>
            </details>
          <?php endif; ?>
          <a href="/account/assets/<?= $kind ?>/<?= (int) $a['id'] ?>/edit" class="btn btn-sm btn-link px-0 mt-1"><i class="bi bi-pencil"></i> Edit details</a>
        </div>
      </div>
    <?php endforeach; ?>
    <?php if (!$assets): ?><div class="col-12"><div class="panel text-body-secondary">Nothing registered yet.</div></div><?php endif; ?>
  </div>
</div>
