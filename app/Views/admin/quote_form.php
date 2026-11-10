<?php $rows = array_pad($prefill, max(4, count($prefill) + 2), ['description' => '', 'qty' => 1, 'price' => '']); ?>
<a href="<?= e(App\Services\Workflow::subjectLink($type, (int) $subject['id'], true)) ?>" class="small"><i class="bi bi-arrow-left"></i> Back</a>
<p class="mt-2">For <strong><?= e($customer['first_name'] . ' ' . $customer['surname']) ?></strong> · <?= e($subject['reference']) ?></p>
<form method="post" action="/admin/quotations/new/<?= e($type) ?>/<?= (int) $subject['id'] ?>" class="panel" novalidate>
  <?= csrf_field() ?>
  <div class="table-responsive">
    <table class="table table-sm align-middle quote-lines">
      <thead><tr><th style="min-width:280px">Description</th><th style="width:110px">Qty</th><th style="width:160px">Unit price (R)</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $i => $r): ?>
          <tr><td><input class="form-control form-control-sm" name="desc[]" value="<?= e($r['description']) ?>" aria-label="Description <?= $i + 1 ?>"></td>
            <td><input class="form-control form-control-sm" name="qty[]" inputmode="decimal" value="<?= e($r['qty']) ?>" aria-label="Quantity <?= $i + 1 ?>"></td>
            <td><input class="form-control form-control-sm" name="price[]" inputmode="decimal" value="<?= e($r['price']) ?>" placeholder="0.00" aria-label="Unit price <?= $i + 1 ?>"></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p class="small text-body-secondary">Empty lines are ignored.</p>
  <div class="row g-3">
    <div class="col-sm-4"><label class="form-label" for="billing">Billing</label><select class="form-select" id="billing" name="billing"><option value="once_off">Once-off</option><option value="monthly"<?= $type !== 'service' ? ' selected' : '' ?>>Monthly</option></select></div>
    <div class="col-sm-4"><label class="form-label" for="valid_until">Valid until</label><input class="form-control" type="date" id="valid_until" name="valid_until" value="<?= date('Y-m-d', strtotime('+14 days')) ?>"></div>
    <div class="col-sm-4 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" id="vat" name="vat" value="1" checked><label class="form-check-label" for="vat">Add VAT (<?= (int) round((float) config('vat_rate') * 100) ?>%)</label></div></div>
    <div class="col-12"><label class="form-label" for="notes">Notes / terms shown to the customer</label><textarea class="form-control" id="notes" name="notes" rows="3"></textarea></div>
  </div>
  <div class="d-flex gap-2 mt-3">
    <button name="send" value="1" class="btn btn-brand">Send to customer</button>
    <button name="send" value="0" class="btn btn-outline-secondary">Save draft</button>
  </div>
</form>
