<?php $O = App\Services\Ops::class; ?>
<div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
  <form class="row g-2 flex-grow-1" method="get" action="<?= $base ?>/incidents">
    <div class="col-sm-4 col-lg-3"><input class="form-control" name="q" value="<?= e($f['q']) ?>" placeholder="Search reference, place, text" aria-label="Search"></div>
    <div class="col-6 col-sm-3 col-lg-2"><select class="form-select" name="status" aria-label="Status"><option value="">All statuses</option><option value="open"<?= $f['status'] === 'open' ? ' selected' : '' ?>>Open</option><?php foreach (App\Services\Incidents::STATUSES as $s): ?><option value="<?= $s ?>"<?= $f['status'] === $s ? ' selected' : '' ?>><?= e($O::label($s)) ?></option><?php endforeach; ?></select></div>
    <div class="col-6 col-sm-3 col-lg-2"><select class="form-select" name="category" aria-label="Category"><option value="">All categories</option><?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>"<?= $f['category'] === (string) $c['id'] ? ' selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-6 col-sm-2 col-lg-1"><select class="form-select" name="priority" aria-label="Priority"><option value="">Priority</option><?php foreach (['critical', 'high', 'normal', 'low'] as $p): ?><option<?= $f['priority'] === $p ? ' selected' : '' ?>><?= $p ?></option><?php endforeach; ?></select></div>
    <div class="col-6 col-sm-3 col-lg-2"><input class="form-control" type="date" name="from" value="<?= e($f['from']) ?>" aria-label="From"></div>
    <div class="col-6 col-sm-3 col-lg-2"><input class="form-control" type="date" name="to" value="<?= e($f['to']) ?>" aria-label="To"></div>
    <div class="col-auto"><button class="btn btn-outline-secondary">Filter</button></div>
  </form>
  <a href="<?= $base ?>/incidents/new" class="btn btn-brand align-self-start"><i class="bi bi-plus-lg"></i> Log incident</a>
</div>
<div class="table-card"><div class="table-responsive"><table class="table table-hover mb-0">
  <thead><tr><th>Reference</th><th>What</th><th>Where</th><th>Reported by</th><th>Priority</th><th>Status</th><th>When</th></tr></thead>
  <tbody>
    <?php foreach ($rows as $r): ?>
      <tr class="<?= $r['source'] === 'panic' && !in_array($r['status'], ['resolved', 'closed'], true) ? 'table-danger' : '' ?>">
        <td><a class="fw-semibold" href="<?= $base ?>/incidents/<?= (int) $r['id'] ?>"><?= e($r['reference']) ?></a></td>
        <td><?= e($r['category_name'] ?? $r['title']) ?><?= $r['source'] === 'panic' ? ' <span class="badge text-bg-danger">PANIC</span>' : '' ?></td>
        <td class="small"><?= e($r['location_text']) ?></td>
        <td class="small"><?= $r['first_name'] ? e($r['first_name'] . ' ' . $r['surname']) : ($r['source'] === 'staff' ? 'Staff' : '—') ?></td>
        <td><?= $O::badge($r['priority']) ?></td><td><?= $O::badge($r['status']) ?></td>
        <td class="small text-nowrap"><?= e(date('j M H:i', strtotime($r['created_at']))) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="7" class="p-4 text-body-secondary">No incidents match.</td></tr><?php endif; ?>
  </tbody>
</table></div></div>
