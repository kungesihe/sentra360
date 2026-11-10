<div class="container py-4">
  <?= App\Core\View::partial('home/_nav', []) ?>
  <?php require BASE_PATH . '/app/Views/partials/flash.php'; ?>
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0"><?= e($title) ?></h1>
    <div class="d-flex gap-2"><a href="/account/assets/property/new" class="btn btn-brand btn-sm">Protect a property</a><a href="/account/assets/vehicle/new" class="btn btn-outline-dark btn-sm">Protect a vehicle</a></div>
  </div>
  <div class="table-card"><div class="table-responsive"><table class="table table-hover mb-0">
    <thead><tr><th>Reference</th><th>Type</th><th>For</th><th>Submitted</th><th>Status</th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr><td><a class="fw-semibold" href="/account/applications/<?= e($r['kind']) ?>/<?= (int) $r['id'] ?>"><?= e($r['reference']) ?></a></td><td><?= $r['kind'] === 'property' ? 'Residential' : 'Vehicle' ?></td><td><?= e($r['label']) ?></td><td class="small"><?= e(date('j M Y', strtotime($r['created_at']))) ?></td><td><?= App\Services\Ops::badge($r['status']) ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="5" class="p-4 text-body-secondary">No applications yet.</td></tr><?php endif; ?>
    </tbody>
  </table></div></div>
</div>
