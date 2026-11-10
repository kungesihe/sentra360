<?php $O = App\Services\Ops::class; ?>
<form class="row g-2 mb-3" method="get" action="/admin/applications">
  <div class="col-sm-4 col-lg-3"><select class="form-select" name="status" aria-label="Status">
    <option value="open"<?= $status === 'open' ? ' selected' : '' ?>>Open (needs work)</option><option value="all"<?= $status === 'all' ? ' selected' : '' ?>>All</option>
    <?php foreach (App\Services\Workflow::APP_STATUSES as $s): ?><option value="<?= $s ?>"<?= $status === $s ? ' selected' : '' ?>><?= e($O::label($s)) ?></option><?php endforeach; ?>
  </select></div>
  <div class="col-sm-3 col-lg-2"><select class="form-select" name="kind" aria-label="Type"><option value="">Residential & vehicle</option><option value="property"<?= $kindF === 'property' ? ' selected' : '' ?>>Residential</option><option value="vehicle"<?= $kindF === 'vehicle' ? ' selected' : '' ?>>Vehicle</option></select></div>
  <div class="col-auto"><button class="btn btn-outline-secondary">Filter</button></div>
</form>
<div class="table-card"><div class="table-responsive"><table class="table table-hover mb-0">
  <thead><tr><th>Reference</th><th>Type</th><th>Customer</th><th>For</th><th>Status</th><th>Updated</th></tr></thead>
  <tbody>
    <?php foreach ($rows as $r): ?>
      <tr><td><a class="fw-semibold" href="/admin/applications/<?= e($r['kind']) ?>/<?= (int) $r['id'] ?>"><?= e($r['reference']) ?></a></td><td><?= $r['kind'] === 'property' ? 'Residential' : 'Vehicle' ?></td><td><?= e($r['first_name'] . ' ' . $r['surname']) ?></td><td class="small"><?= e($r['label']) ?></td><td><?= $O::badge($r['status']) ?></td><td class="small text-nowrap"><?= e(date('j M H:i', strtotime($r['updated_at']))) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="6" class="p-4 text-body-secondary">No applications match.</td></tr><?php endif; ?>
  </tbody>
</table></div></div>
