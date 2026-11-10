<?php
/** A protection plan summary. Needs $plan; optional $cta (['href', 'label']) and $compact. */
$type = $plan['plan_for'];
$icon = App\Services\Plans::DEFAULTS[$type]['icon'] ?? 'shield-check';
$compact ??= false;
$cta ??= null;
?>
<div class="plan-card h-100" data-plan-card="<?= e($type) ?>">
  <div class="d-flex align-items-center gap-2 mb-2">
    <span class="plan-ico"><i class="bi bi-<?= e($icon) ?>"></i></span>
    <div>
      <div class="fw-bold"><?= e($plan['name']) ?></div>
      <div class="plan-price"><?= App\Core\View::partial('shared/price', ['s' => $plan]) ?></div>
    </div>
  </div>
  <?php if ($plan['setup_cents']): ?><div class="small text-body-secondary mb-2">+ <?= money_exact((int) $plan['setup_cents']) ?> once-off <?= $type === 'vehicle' ? 'tracker fitting' : 'installation' ?></div><?php endif; ?>
  <?php if (!$compact): ?>
    <ul class="plan-includes small mb-3">
      <?php foreach (App\Services\Plans::includes($plan) as $line): ?><li><i class="bi bi-check2"></i> <?= e($line) ?></li><?php endforeach; ?>
    </ul>
  <?php endif; ?>
  <?php if ($cta): ?><a href="<?= e($cta[0]) ?>" class="btn btn-brand btn-sm w-100 mt-auto"><?= e($cta[1]) ?></a><?php endif; ?>
</div>
