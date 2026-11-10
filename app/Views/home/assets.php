<?php $isProp = $kind === 'property'; $P = App\Services\Plans::class; ?>
<div class="container py-4">
  <?= App\Core\View::partial('home/_nav', []) ?>
  <?php require BASE_PATH . '/app/Views/partials/flash.php'; ?>
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0"><?= e($title) ?></h1>
    <div class="d-flex gap-2">
      <?php if ($isProp): ?>
        <a href="/account/assets/property/new" class="btn btn-brand"><i class="bi bi-house-add"></i> Add a home</a>
        <a href="/account/assets/property/new?type=business" class="btn btn-outline-dark"><i class="bi bi-shop"></i> Add a business</a>
      <?php else: ?>
        <a href="/account/assets/vehicle/new" class="btn btn-brand"><i class="bi bi-car-front"></i> Add a vehicle</a>
      <?php endif; ?>
    </div>
  </div>
  <div class="row g-3">
    <?php foreach ($assets as $a):
      $plan = $P::forAsset($kind, $a);
      $isBiz = $P::typeFor($kind, $a) === 'business'; ?>
      <div class="col-md-6 col-xl-4">
        <div class="panel h-100 d-flex flex-column">
          <div class="d-flex justify-content-between align-items-start mb-1">
            <div class="fw-semibold"><i class="bi bi-<?= $isProp ? ($isBiz ? 'shop' : 'house') : 'car-front' ?>"></i> <?= $isProp ? e($a['name']) : e($a['make'] . ' ' . $a['model']) ?></div>
            <?= $a['protection_status'] === 'active' ? '<span class="badge text-bg-success"><i class="bi bi-shield-check"></i> Protected</span>' : '<span class="badge text-bg-secondary">Not protected</span>' ?>
          </div>
          <div class="small text-body-secondary mb-3"><?= $isProp ? e($a['address'] . ', ' . $a['suburb'] . ', ' . $a['city']) : e($a['colour'] . ' · ' . $a['registration'] . ($a['year'] ? ' · ' . $a['year'] : '')) ?></div>
          <?php if ($a['app_id'] && !in_array($a['app_status'], ['rejected', 'cancelled'], true) && $a['protection_status'] !== 'active'): ?>
            <div class="small mb-2"><?= e($plan['name'] ?? 'Protection') ?>: <a href="/account/applications/<?= $kind ?>/<?= (int) $a['app_id'] ?>"><?= App\Services\Ops::badge($a['app_status']) ?></a></div>
          <?php elseif ($a['protection_status'] === 'active'): ?>
            <div class="small mb-2"><i class="bi bi-shield-check text-success"></i> Covered by your <?= e($plan['name'] ?? 'protection plan') ?>.</div>
          <?php elseif ($plan): ?>
            <form method="post" action="/account/assets/<?= $kind ?>/<?= (int) $a['id'] ?>/apply" class="plan-signup mb-2">
              <?= csrf_field() ?>
              <div class="small mb-2"><strong><?= e($plan['name']) ?></strong> · <?= App\Core\View::partial('shared/price', ['s' => $plan]) ?><?= $plan['setup_cents'] ? ' <span class="text-body-secondary">+ ' . money_exact((int) $plan['setup_cents']) . ' once-off</span>' : '' ?></div>
              <?php if ($extras): ?>
                <details class="small mb-2"><summary>Add extras (optional)</summary>
                  <?php foreach ($extras as $i => $x): ?>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="extras[]" value="<?= e($x['name']) ?>" id="x<?= $a['id'] . '-' . $i ?>"><label class="form-check-label" for="x<?= $a['id'] . '-' . $i ?>"><?= e($x['name']) ?></label></div>
                  <?php endforeach; ?>
                </details>
              <?php endif; ?>
              <button class="btn btn-sm btn-warning"><i class="bi bi-shield-plus"></i> Get protected</button>
            </form>
          <?php endif; ?>
          <a href="/account/assets/<?= $kind ?>/<?= (int) $a['id'] ?>/edit" class="btn btn-sm btn-link px-0 mt-auto align-self-start"><i class="bi bi-pencil"></i> Edit details</a>
        </div>
      </div>
    <?php endforeach; ?>
    <?php if (!$assets): ?>
      <div class="col-12"><div class="panel text-body-secondary">Nothing here yet. Add <?= $isProp ? 'your home or business' : 'your car' ?> to see its protection plan.</div></div>
      <?php foreach ($plans as $type => $plan): if (($type === 'vehicle') === $isProp) continue; ?>
        <div class="col-md-6 col-xl-4"><?= App\Core\View::partial('shared/plan_card', ['plan' => $plan, 'cta' => ['/account/assets/' . $kind . '/new' . ($type === 'business' ? '?type=business' : ''), $type === 'business' ? 'Add my business' : ($type === 'vehicle' ? 'Add my vehicle' : 'Add my home')]]) ?></div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>
