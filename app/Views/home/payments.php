<div class="container py-4">
  <?= App\Core\View::partial('home/_nav', []) ?>
  <?php require BASE_PATH . '/app/Views/partials/flash.php'; ?>
  <h1 class="h4 mb-1"><?= e($title) ?></h1>
  <p class="small text-body-secondary">Pay by EFT, then upload your proof of payment on the quotation. We confirm it against our bank statement.</p>
  <div class="table-card"><div class="table-responsive"><table class="table mb-0">
    <thead><tr><th>Reference</th><th>Quotation</th><th>Amount</th><th>Method</th><th>Date</th><th>Status</th></tr></thead>
    <tbody>
      <?php foreach ($rows as $p): ?>
        <tr><td><?= e($p['reference']) ?></td><td><?php if ($p['quotation_id']): ?><a href="/account/quotations/<?= (int) $p['quotation_id'] ?>"><?= e($p['quote_ref']) ?></a><?php else: ?>—<?php endif; ?></td><td><?= money_exact((int) $p['amount_cents']) ?></td><td><?= e(strtoupper($p['method'])) ?></td><td class="small"><?= e(date('j M Y', strtotime($p['paid_at'] ?? $p['created_at']))) ?></td>
          <td><?= match ($p['status']) { 'pending' => '<span class="badge text-bg-warning">being checked</span>', 'failed' => '<span class="badge text-bg-danger">not accepted</span>', 'paid' => '<span class="badge text-bg-success">confirmed</span>', default => App\Services\Ops::badge($p['status']) } ?>
            <?= $p['status'] === 'failed' && $p['notes'] ? '<div class="small text-body-secondary">' . e($p['notes']) . '</div>' : '' ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="6" class="p-4 text-body-secondary">No payments yet.</td></tr><?php endif; ?>
    </tbody>
  </table></div></div>
</div>
