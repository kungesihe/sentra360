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
        <?php if ($due > 0): ?>
          <?php if ($payfast): ?>
            <form method="post" action="/account/quotations/<?= (int) $q['id'] ?>/pay"><?= csrf_field() ?><button class="btn btn-brand"><i class="bi bi-credit-card"></i> Pay <?= money_exact($due) ?> online with PayFast</button></form>
            <p class="small text-body-secondary mt-2">You pay on PayFast's secure page. This site never sees your card details.</p>
          <?php endif; ?>
          <div class="alert alert-light border mt-3 mb-0">
            <strong>Pay by EFT or as arranged with the company.</strong> Use reference <strong><?= e($q['reference']) ?></strong>.
            <?php if ($instructions): ?><div class="mt-2" style="white-space:pre-line"><?= e($instructions) ?></div><?php else: ?><div class="mt-1 small">The company will send you its banking details.</div><?php endif; ?>
            <div class="small mt-2 text-body-secondary">Your payment shows here once the company has confirmed it.</div>
          </div>
        <?php else: ?>
          <div class="alert alert-success mb-0">Paid in full. Thank you.</div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
