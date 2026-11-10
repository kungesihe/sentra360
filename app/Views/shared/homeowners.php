<?php /** Homeowner list, shared by the admin and security portals. Needs $base, $members, $q, $status. */ ?>
<form class="row g-2 mb-3" method="get" action="<?= e($base) ?>">
  <div class="col-sm-6 col-lg-4"><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Search name, email or mobile" aria-label="Search"></div>
  <div class="col-sm-3 col-lg-2">
    <select class="form-select" name="status" aria-label="Status">
      <option value="">All statuses</option>
      <?php foreach (['active' => 'Verified', 'pending' => 'Awaiting verification', 'suspended' => 'Suspended'] as $k => $l): ?><option value="<?= $k ?>"<?= $status === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?>
    </select>
  </div>
  <div class="col-auto"><button class="btn btn-outline-secondary">Filter</button></div>
</form>
<div class="table-card">
  <div class="table-responsive">
    <table class="table table-hover">
      <thead><tr><th>Name</th><th>Mobile</th><th>Email</th><th>Status</th><th>Type</th><th>Joined</th></tr></thead>
      <tbody>
      <?php foreach ($members as $m): ?>
        <tr>
          <td><a href="<?= e($base) ?>/<?= (int) $m['id'] ?>" class="fw-semibold"><?= e($m['first_name'] . ' ' . $m['surname']) ?></a></td>
          <td class="text-nowrap"><?= e(mask_mobile($m['mobile'])) ?> <?= $m['mobile_verified_at'] ? '<i class="bi bi-patch-check-fill text-success" title="Verified"></i>' : '' ?></td>
          <td><?= e(mask_email($m['email'])) ?> <?= $m['email_verified_at'] ? '<i class="bi bi-patch-check-fill text-success" title="Verified"></i>' : '' ?></td>
          <td><?= status_badge($m['status']) ?></td>
          <td><?= status_badge($m['client_status']) ?></td>
          <td class="small text-body-secondary text-nowrap"><?= e(date('j M Y', strtotime($m['created_at']))) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$members): ?><tr><td colspan="6" class="text-body-secondary p-4">No homeowners found.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<p class="small text-body-secondary mt-2"><i class="bi bi-eye-slash"></i> Contact details are partly hidden in lists. Opening a homeowner's record shows full details and is recorded in the audit log.</p>
