<div class="container py-4">
  <?= App\Core\View::partial('home/_nav', []) ?>
  <?php require BASE_PATH . '/app/Views/partials/flash.php'; ?>
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><?= e($title) ?></h1>
    <a href="/account/incidents/new" class="btn btn-brand"><i class="bi bi-plus-lg"></i> Report an incident</a>
  </div>
  <div class="table-card">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr><th>Reference</th><th>What happened</th><th>Where</th><th>When</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><a href="/account/incidents/<?= (int) $r['id'] ?>" class="fw-semibold"><?= e($r['reference']) ?></a></td>
            <td><?= e($r['title']) ?><?= $r['source'] === 'panic' ? ' <span class="badge text-bg-danger">panic</span>' : '' ?></td>
            <td class="small"><?= e($r['location_text']) ?></td>
            <td class="small text-nowrap"><?= e(date('j M Y, H:i', strtotime($r['occurred_at']))) ?></td>
            <td><?= App\Services\Ops::badge($r['status']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="5" class="p-4 text-body-secondary">You haven't reported anything yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
