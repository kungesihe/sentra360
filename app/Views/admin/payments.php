<?php $O = App\Services\Ops::class; ?>
<div class="alert <?= $payfast ? 'alert-success' : 'alert-light border' ?> small">
  <?php if ($payfast): ?><i class="bi bi-check-circle"></i> PayFast is configured<?= App\Services\PayFast::sandbox() ? ' in <strong>sandbox (test) mode</strong>' : '' ?>. Customers can pay accepted quotations online.
  <?php else: ?><i class="bi bi-info-circle"></i> <strong>Online payments are not set up.</strong> Customers see your EFT instructions (Branding & profile → How customers pay), and you record payments on each quotation. To enable PayFast, add PAYFAST_MERCHANT_ID, PAYFAST_MERCHANT_KEY and PAYFAST_PASSPHRASE to the server settings.<?php endif; ?>
</div>
<div class="d-flex justify-content-end mb-2"><a class="btn btn-sm btn-outline-secondary" href="/admin/reports/export/payments"><i class="bi bi-file-earmark-spreadsheet"></i> Export to Excel (CSV)</a></div>
<div class="table-card"><div class="table-responsive"><table class="table mb-0">
  <thead><tr><th>Reference</th><th>Customer</th><th>Quotation</th><th>Amount</th><th>Method</th><th>Status</th><th>Date</th></tr></thead>
  <tbody>
    <?php foreach ($rows as $p): ?>
      <tr><td><?= e($p['reference']) ?></td><td><?= e($p['first_name'] . ' ' . $p['surname']) ?></td><td><?php if ($p['quotation_id']): ?><a href="/admin/quotations/<?= (int) $p['quotation_id'] ?>"><?= e($p['quote_ref']) ?></a><?php endif; ?></td><td><?= money_exact((int) $p['amount_cents']) ?></td><td><?= e(strtoupper($p['method'])) ?></td><td><?= $O::badge($p['status']) ?></td><td class="small"><?= e(date('j M Y', strtotime($p['paid_at'] ?? $p['created_at']))) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="7" class="p-4 text-body-secondary">No payments yet.</td></tr><?php endif; ?>
  </tbody>
</table></div></div>
