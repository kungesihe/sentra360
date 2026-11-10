<?php $O = App\Services\Ops::class; ?>
<div class="d-flex flex-wrap gap-2 mb-3">
  <?php foreach (['open' => 'Open', 'confirmed' => 'Confirmed bookings', 'completed' => 'Completed', 'all' => 'All'] as $k => $l): ?>
    <a href="/admin/requests?status=<?= $k ?>" class="btn btn-sm <?= $status === $k ? 'btn-dark' : 'btn-outline-secondary' ?>"><?= $l ?></a>
  <?php endforeach; ?>
</div>
<div class="table-card"><div class="table-responsive"><table class="table table-hover mb-0">
  <thead><tr><th>Reference</th><th>Service</th><th>Customer</th><th>Date</th><th>Location</th><th>Status</th></tr></thead>
  <tbody>
    <?php foreach ($rows as $r): ?>
      <tr><td><a class="fw-semibold" href="/admin/requests/<?= (int) $r['id'] ?>"><?= e($r['reference']) ?></a></td><td><?= e($r['service_name']) ?></td><td><?= e($r['first_name'] . ' ' . $r['surname']) ?></td><td class="small text-nowrap"><?= e($r['requested_date'] . ($r['requested_time'] ? ' ' . substr($r['requested_time'], 0, 5) : '')) ?></td><td class="small"><?= e($r['location_text']) ?></td><td><?= $O::badge($r['status']) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="6" class="p-4 text-body-secondary">Nothing here.</td></tr><?php endif; ?>
  </tbody>
</table></div></div>
