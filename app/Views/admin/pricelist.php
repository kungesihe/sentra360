<?php
$groups = [];
foreach ($rows as $s) {
    $groups[$s['plan_for'] ? 'Protection plans' : $s['category']][] = $s;
}
$rank = fn($c) => ['Protection plans' => 0, 'Add-ons' => 1][$c] ?? 2;
uksort($groups, fn($a, $b) => [$rank($a), $a] <=> [$rank($b), $b]);
$planNames = ['home' => 'Home', 'business' => 'Business', 'vehicle' => 'Vehicle'];
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <p class="small text-body-secondary mb-0">Every service and its price, in one place. <strong>Extra on</strong> = which plans can add it (only plans that don't already include it). Untick <strong>Listed</strong> to hide a service; nothing is deleted.</p>
  <div class="d-flex gap-2"><a href="/pricelist" target="_blank" class="btn btn-outline-dark btn-sm"><i class="bi bi-eye"></i> Customer view</a><a href="/admin/services/new" class="btn btn-brand btn-sm"><i class="bi bi-plus-lg"></i> Add service</a></div>
</div>
<form method="post" action="/admin/pricelist">
  <?= csrf_field() ?>
  <?php foreach ($groups as $cat => $list): $isPlans = $cat === 'Protection plans'; ?>
    <h2 class="h6 text-uppercase text-body-secondary mt-3 mb-2"><?= e($cat) ?></h2>
    <div class="table-card mb-3"><div class="table-responsive"><table class="table table-sm align-middle mb-0">
      <thead><tr><th>Service</th><th style="width:180px">Pricing</th><th style="width:130px">Price (R)</th><th style="width:130px">Per</th><th style="width:<?= $isPlans ? '170' : '230' ?>px"><?= $isPlans ? 'Once-off setup (R)' : 'Extra on' ?></th><th style="width:60px">Listed</th></tr></thead>
      <tbody>
        <?php foreach ($list as $s): $id = (int) $s['id']; $on = explode(',', (string) $s['addon_for']); ?>
          <tr>
            <td><a href="/admin/services/<?= $id ?>" class="fw-semibold"><?= e($s['name']) ?></a></td>
            <td><select class="form-select form-select-sm" name="pricing[<?= $id ?>]" aria-label="Pricing for <?= e($s['name']) ?>">
              <?php foreach (['fixed' => 'Fixed price', 'from' => 'From (starting price)', 'quote' => 'Quotation required'] as $k => $lbl): ?><option value="<?= $k ?>"<?= $s['pricing'] === $k ? ' selected' : '' ?>><?= $lbl ?></option><?php endforeach; ?>
            </select></td>
            <td><input class="form-control form-control-sm" name="price[<?= $id ?>]" inputmode="decimal" value="<?= $s['price_cents'] !== null ? e(number_format($s['price_cents'] / 100, 2, '.', '')) : '' ?>" placeholder="0.00" aria-label="Price for <?= e($s['name']) ?>"></td>
            <td><input class="form-control form-control-sm" name="unit[<?= $id ?>]" maxlength="40" value="<?= e($s['price_unit'] ?? '') ?>" placeholder="per month" aria-label="Unit for <?= e($s['name']) ?>"></td>
            <td>
              <?php if ($isPlans): ?>
                <input class="form-control form-control-sm" name="setup[<?= $id ?>]" inputmode="decimal" value="<?= $s['setup_cents'] ? e(number_format($s['setup_cents'] / 100, 2, '.', '')) : '' ?>" placeholder="none" aria-label="Setup fee for <?= e($s['name']) ?>">
              <?php else: ?>
                <?php foreach ($planNames as $k => $lbl): ?><div class="form-check form-check-inline small me-2"><input class="form-check-input" type="checkbox" name="addon[<?= $id ?>][]" value="<?= $k ?>" id="a<?= $id . $k ?>"<?= in_array($k, $on, true) ? ' checked' : '' ?>><label class="form-check-label" for="a<?= $id . $k ?>"><?= $lbl ?></label></div><?php endforeach; ?>
              <?php endif; ?>
            </td>
            <td class="text-center"><input class="form-check-input" type="checkbox" name="active[<?= $id ?>]" value="1"<?= $s['is_active'] ? ' checked' : '' ?> aria-label="Listed: <?= e($s['name']) ?>"></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div></div>
  <?php endforeach; ?>
  <?php if (!$rows): ?><div class="panel text-body-secondary">No services yet. <a href="/admin/services/new">Add one</a>.</div><?php else: ?>
    <div class="sticky-bottom bg-body py-2"><button class="btn btn-brand">Save</button></div>
  <?php endif; ?>
</form>
