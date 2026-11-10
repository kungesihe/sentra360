<div class="container py-4">
  <?= App\Core\View::partial('home/_nav', []) ?>
  <?php require BASE_PATH . '/app/Views/partials/flash.php'; ?>
  <?php if (!empty($_GET['paid'])): ?><div class="alert alert-info">Thank you. Your payment shows as <strong>paid</strong> once PayFast confirms it to us, usually within a minute.</div><?php endif; ?>
  <?php if (!empty($_GET['cancelled'])): ?><div class="alert alert-warning">The online payment was cancelled. Nothing was charged.</div><?php endif; ?>
  <h1 class="h4 mb-3"><?= e($title) ?></h1>
  <div class="table-card"><div class="table-responsive"><table class="table mb-0">
    <thead><tr><th>Reference</th><th>Quotation</th><th>Amount</th><th>Method</th><th>Date</th><th>Status</th></tr></thead>
    <tbody>
      <?php foreach ($rows as $p): ?>
        <tr><td><?= e($p['reference']) ?></td><td><?= e($p['quote_ref'] ?? '—') ?></td><td><?= money_exact((int) $p['amount_cents']) ?></td><td><?= e(strtoupper($p['method'])) ?></td><td class="small"><?= e(date('j M Y', strtotime($p['paid_at'] ?? $p['created_at']))) ?></td><td><?= App\Services\Ops::badge($p['status']) ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="6" class="p-4 text-body-secondary">No payments yet.</td></tr><?php endif; ?>
    </tbody>
  </table></div></div>
</div>
