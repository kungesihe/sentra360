<?php $public = !App\Core\Auth::user(); $groups = []; foreach ($services as $s) { $groups[$s['category']][] = $s; } ?>
<div class="container py-4">
  <?php if (!$public): ?><?= App\Core\View::partial('home/_nav', []) ?><?php endif; ?>
  <?php require BASE_PATH . '/app/Views/partials/flash.php'; ?>
  <h1 class="h4">Services from <?= e(tenant()['display_name']) ?></h1>
  <p class="text-body-secondary">Choose a service and send a request. We'll confirm availability and pricing before anything is booked. <a href="<?= $public ? '/pricelist' : '/account/pricelist' ?>">See the full price list</a>.</p>
  <?php if (App\Services\Plans::all()): ?>
    <a href="<?= $public ? '/pricelist' : '/account/assets/property' ?>" class="alert alert-success d-flex align-items-center gap-3 text-decoration-none">
      <i class="bi bi-shield-check fs-3"></i>
      <div><strong>Looking for monthly protection?</strong> Home, vehicle tracking and business alarm plans, all with 24/7 armed response. <span class="text-decoration-underline">See the plans</span></div>
    </a>
  <?php endif; ?>
  <?php foreach ($groups as $cat => $list): ?>
    <h2 class="h6 text-uppercase text-body-secondary mt-4 mb-3"><?= e($cat) ?></h2>
    <div class="row g-3">
      <?php foreach ($list as $s): ?>
        <div class="col-md-6 col-xl-4">
          <a class="action-card h-100" href="<?= $public ? '/services/' : '/account/services/' ?><?= (int) $s['id'] ?>">
            <div class="fw-semibold mb-1"><?= e($s['name']) ?></div>
            <p class="small text-body-secondary mb-2"><?= e(mb_strimwidth($s['description'], 0, 140, '…')) ?></p>
            <div class="small"><?= App\Core\View::partial('shared/price', ['s' => $s]) ?></div>
          </a>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>
  <?php if (!$services): ?><div class="panel text-body-secondary">No services are listed yet.</div><?php endif; ?>
</div>
