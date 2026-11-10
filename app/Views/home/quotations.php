<div class="container py-4">
  <?= App\Core\View::partial('home/_nav', []) ?>
  <?php require BASE_PATH . '/app/Views/partials/flash.php'; ?>
  <h1 class="h4 mb-3"><?= e($title) ?></h1>
  <div class="table-card"><div class="table-responsive"><table class="table table-hover mb-0">
    <thead><tr><th>Reference</th><th>For</th><th>Total</th><th>Sent</th><th>Status</th></tr></thead>
    <tbody>
      <?php foreach ($rows as $q): ?>
        <tr class="<?= $q['status'] === 'sent' ? 'table-warning' : '' ?>"><td><a class="fw-semibold" href="/account/quotations/<?= (int) $q['id'] ?>"><?= e($q['reference']) ?></a></td><td><?= ['property' => 'Residential protection', 'vehicle' => 'Vehicle protection', 'service' => 'Service booking'][$q['subject_type']] ?></td><td><?= money_exact((int) $q['total_cents']) ?><?= $q['billing'] === 'monthly' ? '/month' : '' ?></td><td class="small"><?= e($q['sent_at'] ? date('j M Y', strtotime($q['sent_at'])) : '') ?></td><td><?= App\Services\Ops::badge($q['status'] === 'sent' ? 'awaiting_customer_acceptance' : $q['status']) ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="5" class="p-4 text-body-secondary">No quotations yet.</td></tr><?php endif; ?>
    </tbody>
  </table></div></div>
</div>
