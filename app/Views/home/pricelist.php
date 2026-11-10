<?php
$public = !App\Core\Auth::user();
$t = tenant();
$plans = App\Services\Plans::all();
$groups = [];
foreach ($services as $s) {
    if (!$s['plan_for']) {
        $groups[$s['category']][] = $s;
    }
}
$rank = fn($c) => ['Add-ons' => 0][$c] ?? 1;
uksort($groups, fn($a, $b) => [$rank($a), $a] <=> [$rank($b), $b]);
?>
<div class="container py-4">
  <?php if (!$public): ?><?= App\Core\View::partial('home/_nav', []) ?><?php endif; ?>
  <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
    <div>
      <h1 class="h4 mb-1">Price list</h1>
      <p class="text-body-secondary mb-0"><?= e($t['display_name']) ?> · <?= e(area()['name']) ?> and surrounding areas. Prices exclude VAT (15%). Your quotation confirms the final price.</p>
    </div>
    <button type="button" class="btn btn-outline-dark btn-sm d-print-none" data-print><i class="bi bi-printer"></i> Print</button>
  </div>

  <?php if ($plans): ?>
    <h2 class="h5 mt-2 mb-1">Monthly protection plans</h2>
    <p class="small text-body-secondary mb-3">Every plan includes 24/7 armed response, a priority panic button with a WhatsApp call-back, and incident reports handled first.</p>
    <div class="row g-3 mb-4">
      <?php foreach ($plans as $type => $plan): ?>
        <div class="col-md-4"><?= App\Core\View::partial('shared/plan_card', ['plan' => $plan, 'cta' => [
            $public ? '/register' : '/account/assets/' . App\Services\Plans::DEFAULTS[$type]['kind'] . '/new' . ($type === 'business' ? '?type=business' : ''),
            $public ? 'Join and get protected' : ($type === 'vehicle' ? 'Protect my vehicle' : ($type === 'business' ? 'Protect my business' : 'Protect my home')),
        ]]) ?></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php foreach ($groups as $cat => $list): ?>
    <div class="table-card mb-3">
      <div class="p-3 border-bottom fw-semibold"><?= e($cat === 'Add-ons' ? 'Add-ons to a plan' : $cat) ?></div>
      <div class="table-responsive"><table class="table mb-0 align-middle">
        <tbody>
          <?php foreach ($list as $s): ?>
            <tr>
              <td>
                <?php if ($cat === 'Add-ons'): ?><span class="fw-semibold"><?= e($s['name']) ?></span><?php else: ?><a class="fw-semibold" href="<?= $public ? '/services/' : '/account/services/' ?><?= (int) $s['id'] ?>"><?= e($s['name']) ?></a><?php endif; ?>
                <div class="small text-body-secondary"><?= e(mb_strimwidth($s['description'], 0, 120, '…')) ?></div>
                <?php if ($s['addon_for']): ?><div class="small text-success">Can be added to the <?= e(implode(' or ', array_map('ucfirst', explode(',', $s['addon_for'])))) ?> plan</div><?php endif; ?>
              </td>
              <td class="text-end text-nowrap"><?= App\Core\View::partial('shared/price', ['s' => $s]) ?><?= $s['pricing'] === 'quote' && $s['price_unit'] ? '<div class="small text-body-secondary">' . e($s['price_unit']) . '</div>' : '' ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>
  <?php endforeach; ?>
  <?php if (!$services): ?><div class="panel text-body-secondary">No prices are published yet.</div><?php endif; ?>
</div>
