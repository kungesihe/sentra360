<?php $O = App\Services\Ops::class; ?>
<div class="alert alert-light border small"><i class="bi bi-bank"></i> Customers pay by <strong>EFT</strong> and upload their proof of payment in the app. Check each one against your bank statement before approving: approving a payment confirms the booking or lets you activate the protection plan. Your banking details are under Settings → Branding (How customers pay).</div>

<h2 class="h6 mb-2">Proofs of payment to check <?= $proofs ? '<span class="badge text-bg-warning">' . count($proofs) . '</span>' : '' ?></h2>
<div class="table-card mb-4"><div class="table-responsive"><table class="table align-middle mb-0">
  <thead><tr><th>Customer</th><th>Quotation</th><th>Amount</th><th>Their reference</th><th>Proof</th><th>Uploaded</th><th></th></tr></thead>
  <tbody>
    <?php foreach ($proofs as $p): ?>
      <tr>
        <td><?= e($p['first_name'] . ' ' . $p['surname']) ?></td>
        <td><?php if ($p['quotation_id']): ?><a href="/admin/quotations/<?= (int) $p['quotation_id'] ?>"><?= e($p['quote_ref']) ?></a><div class="small text-body-secondary">total <?= money_exact((int) $p['total_cents']) ?></div><?php endif; ?></td>
        <td class="fw-semibold"><?= money_exact((int) $p['amount_cents']) ?></td>
        <td class="small"><?= e($p['provider_reference'] ?? '—') ?></td>
        <td><?php if ($p['file_id']): ?><a href="/files/<?= (int) $p['file_id'] ?>" target="_blank" class="btn btn-sm btn-outline-dark"><i class="bi bi-file-earmark-text"></i> View</a><?php else: ?><span class="small text-danger">no file</span><?php endif; ?></td>
        <td class="small text-nowrap"><?= e(date('j M H:i', strtotime($p['created_at']))) ?></td>
        <td class="text-end">
          <div class="d-flex flex-wrap gap-1 justify-content-end">
            <form method="post" action="/admin/payments/<?= (int) $p['id'] ?>/review"><?= csrf_field() ?><input type="hidden" name="decision" value="approve"><button class="btn btn-sm btn-success">Approve</button></form>
            <details><summary class="btn btn-sm btn-outline-danger">Reject</summary>
              <form method="post" action="/admin/payments/<?= (int) $p['id'] ?>/review" class="mt-1"><?= csrf_field() ?><input type="hidden" name="decision" value="reject"><input class="form-control form-control-sm mb-1" name="reason" maxlength="200" placeholder="Why (sent to the customer)"><button class="btn btn-sm btn-danger">Reject</button></form>
            </details>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$proofs): ?><tr><td colspan="7" class="p-3 text-body-secondary">Nothing to check.</td></tr><?php endif; ?>
  </tbody>
</table></div></div>

<div class="d-flex justify-content-between align-items-center mb-2"><h2 class="h6 mb-0">All payments</h2><a class="btn btn-sm btn-outline-secondary" href="/admin/reports/export/payments"><i class="bi bi-file-earmark-spreadsheet"></i> Export to Excel (CSV)</a></div>
<div class="table-card"><div class="table-responsive"><table class="table mb-0">
  <thead><tr><th>Reference</th><th>Customer</th><th>Quotation</th><th>Amount</th><th>Method</th><th>Status</th><th>Date</th></tr></thead>
  <tbody>
    <?php foreach ($rows as $p): ?>
      <tr><td><?= e($p['reference']) ?></td><td><?= e($p['first_name'] . ' ' . $p['surname']) ?></td><td><?php if ($p['quotation_id']): ?><a href="/admin/quotations/<?= (int) $p['quotation_id'] ?>"><?= e($p['quote_ref']) ?></a><?php endif; ?></td><td><?= money_exact((int) $p['amount_cents']) ?></td><td><?= e(strtoupper($p['method'])) ?></td><td><?= $p['status'] === 'pending' ? '<span class="badge text-bg-warning">to check</span>' : ($p['status'] === 'failed' ? '<span class="badge text-bg-danger">rejected</span>' : $O::badge($p['status'])) ?></td><td class="small"><?= e(date('j M Y', strtotime($p['paid_at'] ?? $p['created_at']))) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="7" class="p-4 text-body-secondary">No payments yet.</td></tr><?php endif; ?>
  </tbody>
</table></div></div>
