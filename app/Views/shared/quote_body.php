<?php /** Printable quotation body. Needs $q, $items. */ $t = tenant(); ?>
<div class="quote-doc">
  <div class="d-flex justify-content-between flex-wrap gap-3 mb-4">
    <div>
      <?php if ($t['logo_path']): ?><img src="<?= e($t['logo_path']) ?>" alt="" class="tenant-logo mb-2"><?php endif; ?>
      <div class="fw-bold"><?= e($t['display_name']) ?></div>
      <div class="small text-body-secondary"><?= e($t['physical_address'] ?? '') ?><br><?= e($t['contact_phone'] ?? '') ?> <?= e($t['public_email'] ?? '') ?></div>
    </div>
    <div class="text-end">
      <div class="h4 mb-0">QUOTATION</div>
      <div><?= e($q['reference']) ?></div>
      <div class="small text-body-secondary">Date: <?= e(date('j M Y', strtotime($q['sent_at'] ?? $q['created_at']))) ?><?= $q['valid_until'] ? '<br>Valid until: ' . e(date('j M Y', strtotime($q['valid_until']))) : '' ?></div>
    </div>
  </div>
  <div class="small mb-3"><strong>For:</strong> <?= e($q['first_name'] . ' ' . $q['surname']) ?> · <?= e($q['email']) ?> · <?= e($q['mobile']) ?></div>
  <table class="table table-sm">
    <thead><tr><th>Description</th><th class="text-end">Qty</th><th class="text-end">Unit price</th><th class="text-end">Amount</th></tr></thead>
    <tbody>
      <?php foreach ($items as $it): ?><tr><td><?= e($it['description']) ?></td><td class="text-end"><?= rtrim(rtrim(number_format((float) $it['quantity'], 2, '.', ''), '0'), '.') ?></td><td class="text-end"><?= money_exact((int) $it['unit_price_cents']) ?></td><td class="text-end"><?= money_exact((int) $it['line_total_cents']) ?></td></tr><?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr><td colspan="3" class="text-end">Subtotal</td><td class="text-end"><?= money_exact((int) $q['subtotal_cents']) ?></td></tr>
      <?php if ($q['include_vat']): ?><tr><td colspan="3" class="text-end">VAT (<?= (int) round((float) config('vat_rate') * 100) ?>%)</td><td class="text-end"><?= money_exact((int) $q['vat_cents']) ?></td></tr><?php endif; ?>
      <tr class="fw-bold"><td colspan="3" class="text-end">Total<?= $q['billing'] === 'monthly' ? ' per month' : '' ?></td><td class="text-end"><?= money_exact((int) $q['total_cents']) ?></td></tr>
    </tfoot>
  </table>
  <?php if ($q['notes']): ?><div class="small" style="white-space:pre-line"><strong>Notes:</strong> <?= e($q['notes']) ?></div><?php endif; ?>
</div>
