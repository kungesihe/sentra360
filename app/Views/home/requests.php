<div class="container py-4">
  <?= App\Core\View::partial('home/_nav', []) ?>
  <?php require BASE_PATH . '/app/Views/partials/flash.php'; ?>
  <div class="d-flex justify-content-between align-items-center mb-3"><h1 class="h4 mb-0"><?= e($title) ?></h1><a href="/account/services" class="btn btn-brand btn-sm">Browse services</a></div>
  <div class="table-card"><div class="table-responsive"><table class="table table-hover mb-0">
    <thead><tr><th>Reference</th><th>Service</th><th>Date</th><th>Location</th><th>Status</th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr><td><a class="fw-semibold" href="/account/requests/<?= (int) $r['id'] ?>"><?= e($r['reference']) ?></a></td><td><?= e($r['service_name']) ?></td><td class="small text-nowrap"><?= e($r['requested_date'] . ($r['requested_time'] ? ' ' . substr($r['requested_time'], 0, 5) : '')) ?></td><td class="small"><?= e($r['location_text']) ?></td><td><?= App\Services\Ops::badge($r['status']) ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="5" class="p-4 text-body-secondary">No requests yet.</td></tr><?php endif; ?>
    </tbody>
  </table></div></div>
</div>
