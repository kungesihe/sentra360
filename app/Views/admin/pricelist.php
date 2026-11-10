<?php
$plans = array_values(array_filter($rows, fn($s) => $s['plan_for']));
$onceOff = array_values(array_filter($rows, fn($s) => !$s['plan_for']));
usort($onceOff, fn($a, $b) => [!$a['is_active'], $a['category'], $a['name']] <=> [!$b['is_active'], $b['category'], $b['name']]);
$sections = [
    ['Monthly plans', 'Collected every month. Every plan includes 24/7 armed response.', $plans, true],
    ['Once-off services', 'When our people are needed: guards, events and escorts. Booked and quoted per job.', $onceOff, false],
];
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <p class="small text-body-secondary mb-0">All services and prices on one screen. Untick <strong>Listed</strong> to hide a service from customers; nothing is deleted.</p>
  <div class="d-flex gap-2"><a href="/services" target="_blank" class="btn btn-outline-dark btn-sm"><i class="bi bi-eye"></i> Customer view</a><a href="/admin/services/new" class="btn btn-brand btn-sm"><i class="bi bi-plus-lg"></i> Add once-off service</a></div>
</div>
<form method="post" action="/admin/pricelist">
  <?= csrf_field() ?>
  <?php foreach ($sections as [$heading, $note, $list, $isPlans]): if (!$list) continue; ?>
    <h2 class="h5 mt-3 mb-0"><?= e($heading) ?></h2>
    <p class="small text-body-secondary mb-2"><?= e($note) ?></p>
    <div class="table-card mb-4"><div class="table-responsive"><table class="table table-sm align-middle mb-0">
      <thead><tr><th>Service</th><th style="width:180px">Pricing</th><th style="width:130px">Price (R)</th><th style="width:130px">Per</th><?php if ($isPlans): ?><th style="width:160px">Once-off setup (R)</th><?php endif; ?><th style="width:60px">Listed</th></tr></thead>
      <tbody>
        <?php foreach ($list as $s): $id = (int) $s['id']; ?>
          <tr class="<?= $s['is_active'] ? '' : 'opacity-50' ?>">
            <td><a href="/admin/services/<?= $id ?>" class="fw-semibold"><?= e($s['name']) ?></a><?= $isPlans ? '' : '<div class="small text-body-secondary">' . e($s['category']) . '</div>' ?></td>
            <td><select class="form-select form-select-sm" name="pricing[<?= $id ?>]" aria-label="Pricing for <?= e($s['name']) ?>">
              <?php foreach (['fixed' => 'Fixed price', 'from' => 'From (starting price)', 'quote' => 'Quotation required'] as $k => $lbl): ?><option value="<?= $k ?>"<?= $s['pricing'] === $k ? ' selected' : '' ?>><?= $lbl ?></option><?php endforeach; ?>
            </select></td>
            <td><input class="form-control form-control-sm" name="price[<?= $id ?>]" inputmode="decimal" value="<?= $s['price_cents'] !== null ? e(number_format($s['price_cents'] / 100, 2, '.', '')) : '' ?>" placeholder="0.00" aria-label="Price for <?= e($s['name']) ?>"></td>
            <td><input class="form-control form-control-sm" name="unit[<?= $id ?>]" maxlength="40" value="<?= e($s['price_unit'] ?? '') ?>" placeholder="<?= $isPlans ? 'per month' : 'per shift' ?>" aria-label="Unit for <?= e($s['name']) ?>"></td>
            <?php if ($isPlans): ?><td><input class="form-control form-control-sm" name="setup[<?= $id ?>]" inputmode="decimal" value="<?= $s['setup_cents'] ? e(number_format($s['setup_cents'] / 100, 2, '.', '')) : '' ?>" placeholder="none" aria-label="Setup fee for <?= e($s['name']) ?>"></td><?php endif; ?>
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
