<?php $O = App\Services\Ops::class; $due = (int) $q['total_cents'] - $paid; ?>
<div class="d-flex justify-content-between align-items-center mb-3 no-print">
  <a href="<?= e($subjectLink) ?>" class="small"><i class="bi bi-arrow-left"></i> Back to the request</a>
  <div class="d-flex gap-2 align-items-center"><?= $O::badge($q['status']) ?><button type="button" class="btn btn-sm btn-outline-secondary" data-print><i class="bi bi-printer"></i> Print / PDF</button></div>
</div>
<div class="row g-4">
  <div class="col-xl-8"><div class="panel"><?= App\Core\View::partial('shared/quote_body', ['q' => $q, 'items' => $items]) ?></div></div>
  <div class="col-xl-4 no-print">
    <?php if (in_array($q['status'], ['draft', 'sent'], true)): ?>
      <form method="post" action="/admin/quotations/<?= (int) $q['id'] ?>/action" class="panel mb-3 d-flex flex-wrap gap-2">
        <?= csrf_field() ?>
        <?php if ($q['status'] === 'draft'): ?><button name="action" value="send" class="btn btn-brand">Send to customer</button><?php endif; ?>
        <button name="action" value="cancel" class="btn btn-outline-danger" data-confirm-click="Cancel this quotation?">Cancel quotation</button>
      </form>
    <?php endif; ?>
    <div class="panel">
      <h2 class="h6">Payments</h2>
      <?php foreach ($payments as $p): ?><div class="small d-flex justify-content-between border-bottom py-1"><span><?= e($p['reference']) ?> · <?= e(strtoupper($p['method'])) ?></span><span><?= money_exact((int) $p['amount_cents']) ?> <?= $O::badge($p['status']) ?></span></div><?php endforeach; ?>
      <p class="small mt-2 mb-2">Paid <strong><?= money_exact($paid) ?></strong> of <?= money_exact((int) $q['total_cents']) ?></p>
      <?php if ($q['status'] === 'accepted' && $due > 0): ?>
        <form method="post" action="/admin/quotations/<?= (int) $q['id'] ?>/payment" novalidate>
          <?= csrf_field() ?>
          <div class="row g-2">
            <div class="col-6"><input class="form-control form-control-sm" name="amount" inputmode="decimal" value="<?= number_format($due / 100, 2, '.', '') ?>" aria-label="Amount received"></div>
            <div class="col-6"><select class="form-select form-select-sm" name="method" aria-label="Method"><option value="eft">EFT</option><option value="cash">Cash</option><option value="card">Card (in person)</option><option value="debit_order">Debit order</option><option value="other">Other</option></select></div>
            <div class="col-12"><input class="form-control form-control-sm" name="provider_reference" placeholder="Bank / proof-of-payment reference" aria-label="Reference"></div>
          </div>
          <button class="btn btn-sm btn-success mt-2">Record payment received</button>
        </form>
      <?php elseif ($q['status'] !== 'accepted'): ?>
        <p class="small text-body-secondary mb-0">Payments can be recorded once the customer accepts.</p>
      <?php endif; ?>
    </div>
  </div>
</div>
