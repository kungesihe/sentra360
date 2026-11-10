<?php $due = (int) $q['total_cents'] - $paid; ?>
<div class="container py-4" style="max-width:900px">
  <div class="no-print"><?= App\Core\View::partial('home/_nav', ['active' => '/account/quotations']) ?></div>
  <?php require BASE_PATH . '/app/Views/partials/flash.php'; ?>
  <div class="d-flex justify-content-between align-items-center mb-3 no-print">
    <a href="<?= e($subjectLink) ?>" class="small"><i class="bi bi-arrow-left"></i> Back to the request</a>
    <button type="button" class="btn btn-sm btn-outline-secondary" data-print><i class="bi bi-printer"></i> Print / save as PDF</button>
  </div>
  <div class="panel mb-4"><?= App\Core\View::partial('shared/quote_body', ['q' => $q, 'items' => $items]) ?></div>

  <div class="no-print">
    <?php if ($q['status'] === 'sent'): ?>
      <div class="panel">
        <h2 class="h5">Your decision</h2>
        <p class="small text-body-secondary">Accepting means you agree to the services and price above. Payment and activation follow.</p>
        <form method="post" action="/account/quotations/<?= (int) $q['id'] ?>/respond" class="d-inline"><?= csrf_field() ?><input type="hidden" name="decision" value="accept"><button class="btn btn-success px-4">Accept quotation</button></form>
        <details class="d-inline-block ms-2"><summary class="btn btn-outline-danger">Decline</summary>
          <form method="post" action="/account/quotations/<?= (int) $q['id'] ?>/respond" class="mt-2"><?= csrf_field() ?><input type="hidden" name="decision" value="reject"><input class="form-control form-control-sm mb-2" name="reason" maxlength="500" placeholder="Reason (optional)"><button class="btn btn-sm btn-danger">Decline quotation</button></form>
        </details>
      </div>
    <?php elseif ($q['status'] === 'accepted'): ?>
      <div class="panel">
        <h2 class="h5">Payment</h2>
        <p>Paid so far: <strong><?= money_exact($paid) ?></strong> of <?= money_exact((int) $q['total_cents']) ?>.</p>
        <?php foreach ($payments as $pm): if ($pm['status'] === 'pending'): ?>
          <div class="alert alert-info small"><i class="bi bi-hourglass-split"></i> Proof of payment for <?= money_exact((int) $pm['amount_cents']) ?> uploaded <?= e(date('j M H:i', strtotime($pm['created_at']))) ?>. We're checking it against our bank statement.</div>
        <?php elseif ($pm['status'] === 'failed'): ?>
          <div class="alert alert-danger small"><i class="bi bi-x-circle"></i> Proof of payment for <?= money_exact((int) $pm['amount_cents']) ?> was not accepted<?= $pm['notes'] ? ': ' . e($pm['notes']) : '' ?>. Please check and upload it again.</div>
        <?php endif; endforeach; ?>
        <?php if ($due > 0): ?>
          <div class="alert alert-light border">
            <strong>1. Pay <?= money_exact($due) ?> by EFT</strong> using reference <strong><?= e($q['reference']) ?></strong>.
            <?php if ($instructions): ?><div class="mt-2" style="white-space:pre-line"><?= e($instructions) ?></div><?php else: ?><div class="mt-1 small">Our banking details are on your quotation, or WhatsApp us for them.</div><?php endif; ?>
          </div>
          <form method="post" action="/account/quotations/<?= (int) $q['id'] ?>/proof" enctype="multipart/form-data" class="border rounded-3 p-3">
            <?= csrf_field() ?>
            <div class="fw-semibold mb-2">2. Upload your proof of payment</div>
            <div class="row g-2">
              <div class="col-sm-4"><label class="form-label small" for="amount">Amount paid (R)</label><input class="form-control" id="amount" name="amount" inputmode="decimal" required value="<?= e(number_format($due / 100, 2, '.', '')) ?>"></div>
              <div class="col-sm-8"><label class="form-label small" for="bank_reference">Reference you used (optional)</label><input class="form-control" id="bank_reference" name="bank_reference" maxlength="100" value="<?= e($q['reference']) ?>"></div>
              <div class="col-12"><label class="form-label small" for="pop">Proof of payment (PDF or photo)</label><input class="form-control" type="file" id="pop" name="pop[]" required accept="application/pdf,image/png,image/jpeg,image/webp"></div>
            </div>
            <button class="btn btn-brand mt-3"><i class="bi bi-upload"></i> Send proof of payment</button>
            <div class="small text-body-secondary mt-2">We check it against our bank statement, usually the same working day, and your <?= $q['subject_type'] === 'service' ? 'booking is confirmed' : 'protection is activated' ?> once it clears.</div>
          </form>
        <?php else: ?>
          <div class="alert alert-success mb-0">Paid in full. Thank you.</div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
