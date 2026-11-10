<?php $O = App\Services\Ops::class; ?>
<div class="d-flex flex-wrap gap-2 mb-3">
  <?php foreach (['' => 'All', 'draft' => 'Drafts', 'sent' => 'Awaiting customer', 'accepted' => 'Accepted', 'rejected' => 'Declined'] as $k => $l): ?>
    <a href="/admin/quotations<?= $k ? '?status=' . $k : '' ?>" class="btn btn-sm <?= $status === $k ? 'btn-dark' : 'btn-outline-secondary' ?>"><?= $l ?></a>
  <?php endforeach; ?>
</div>
<div class="table-card"><div class="table-responsive"><table class="table table-hover mb-0">
  <thead><tr><th>Reference</th><th>Customer</th><th>For</th><th>Total</th><th>Status</th><th>Created</th></tr></thead>
  <tbody>
    <?php foreach ($rows as $q): ?>
      <tr><td><a class="fw-semibold" href="/admin/quotations/<?= (int) $q['id'] ?>"><?= e($q['reference']) ?></a></td><td><?= e($q['first_name'] . ' ' . $q['surname']) ?></td><td><?= ['property' => 'Residential', 'vehicle' => 'Vehicle', 'service' => 'Service'][$q['subject_type']] ?></td><td><?= money_exact((int) $q['total_cents']) ?><?= $q['billing'] === 'monthly' ? '/m' : '' ?></td><td><?= $O::badge($q['status']) ?></td><td class="small"><?= e(date('j M Y', strtotime($q['created_at']))) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="6" class="p-4 text-body-secondary">No quotations.</td></tr><?php endif; ?>
  </tbody>
</table></div></div>
