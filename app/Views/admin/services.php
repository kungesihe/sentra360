<div class="d-flex justify-content-between align-items-center mb-3">
  <p class="small text-body-secondary mb-0">These appear in the services marketplace for homeowners and on the public <a href="/services" target="_blank">services page</a>.</p>
  <a href="/admin/services/new" class="btn btn-brand"><i class="bi bi-plus-lg"></i> Add service</a>
</div>
<div class="table-card"><div class="table-responsive"><table class="table table-hover mb-0">
  <thead><tr><th>Service</th><th>Category</th><th>Price</th><th>Requests</th><th>Visible</th></tr></thead>
  <tbody>
    <?php foreach ($rows as $s): ?>
      <tr class="<?= $s['is_active'] ? '' : 'text-body-secondary' ?>"><td><a class="fw-semibold" href="/admin/services/<?= (int) $s['id'] ?>"><?= e($s['name']) ?></a></td><td><?= e($s['category']) ?></td><td class="small"><?= App\Core\View::partial('shared/price', ['s' => $s]) ?></td><td><?= (int) $s['requests'] ?></td><td><?= $s['is_active'] ? '<span class="badge text-bg-success">Listed</span>' : '<span class="badge text-bg-secondary">Hidden</span>' ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="5" class="p-4 text-body-secondary">No services yet.</td></tr><?php endif; ?>
  </tbody>
</table></div></div>
