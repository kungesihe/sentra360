<?php $public = !App\Core\Auth::user(); $t = tenant(); $groups = []; foreach ($services as $s) { $groups[$s['category']][] = $s; }  uksort($groups, fn($a, $b) => [$a !== 'Residential', $a] <=> [$b !== 'Residential', $b]); ?>
<div class="container py-4">
  <?php if (!$public): ?><?= App\Core\View::partial('home/_nav', []) ?><?php endif; ?>
  <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
    <div>
      <h1 class="h4 mb-1">Price list</h1>
      <p class="text-body-secondary mb-0">Services and protection from <?= e($t['display_name']) ?>. Prices exclude VAT (15%). Final prices are confirmed in your quotation.</p>
    </div>
    <button type="button" class="btn btn-outline-dark btn-sm d-print-none" data-print><i class="bi bi-printer"></i> Print</button>
  </div>
  <?php foreach ($groups as $cat => $list): ?>
    <div class="table-card mb-3">
      <div class="p-3 border-bottom fw-semibold"><?= e($cat) ?><?= $cat === 'Residential' ? ' <span class="badge text-bg-success ms-1">Home protection</span>' : '' ?></div>
      <div class="table-responsive"><table class="table mb-0 align-middle">
        <tbody>
          <?php foreach ($list as $s): ?>
            <tr>
              <td><a class="fw-semibold" href="<?= $public ? '/services/' : '/account/services/' ?><?= (int) $s['id'] ?>"><?= e($s['name']) ?></a><div class="small text-body-secondary"><?= e(mb_strimwidth($s['description'], 0, 120, '…')) ?></div></td>
              <td class="text-end text-nowrap"><?= App\Core\View::partial('shared/price', ['s' => $s]) ?><?= $s['pricing'] === 'quote' && $s['price_unit'] ? '<div class="small text-body-secondary">' . e($s['price_unit']) . '</div>' : '' ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>
  <?php endforeach; ?>
  <?php if (!$services): ?><div class="panel text-body-secondary">No prices are published yet.</div><?php endif; ?>
  <div class="d-flex flex-wrap gap-2 d-print-none">
    <a href="<?= $public ? '/register' : '/account/assets/property/new' ?>" class="btn btn-brand"><i class="bi bi-house-check"></i> Protect my home</a>
    <a href="<?= $public ? '/services' : '/account/services' ?>" class="btn btn-outline-dark">Book a service</a>
  </div>
</div>
