<div class="row g-3 mb-4">
  <?php foreach ([
    ['Homeowners', $stats['homeowners'] ?? 0, 'house-door'],
    ['Verified', $stats['verified'] ?? 0, 'patch-check'],
    ['Awaiting verification', $stats['pending'] ?? 0, 'hourglass-split'],
    ['New this week', $stats['new_this_week'] ?? 0, 'graph-up-arrow'],
    ['Active clients', $stats['clients'] ?? 0, 'shield-check'],
    ['Security logins', $stats['security_staff'] ?? 0, 'shield-shaded'],
  ] as [$label, $val, $ico]): ?>
    <div class="col-6 col-md-4 col-xl-2"><div class="stat"><div class="label"><i class="bi bi-<?= $ico ?>"></i> <?= $label ?></div><div class="value"><?= (int) $val ?></div></div></div>
  <?php endforeach; ?>
</div>
<div class="row g-4">
  <div class="col-lg-7">
    <div class="table-card">
      <div class="d-flex justify-content-between align-items-center p-3 border-bottom"><h2 class="h6 mb-0">Latest homeowner registrations</h2><a href="/admin/homeowners" class="small">View all</a></div>
      <table class="table">
        <tbody>
        <?php foreach ($recent as $r): ?>
          <tr><td><a href="/admin/homeowners/<?= (int) $r['id'] ?>"><?= e($r['first_name'] . ' ' . $r['surname']) ?></a></td><td><?= status_badge($r['status']) ?></td><td class="text-end small text-body-secondary"><?= e(date('j M, H:i', strtotime($r['created_at']))) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$recent): ?><tr><td class="text-body-secondary p-4">No registrations yet. Share <strong><?= e(app_url('/register')) ?></strong> with your community.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="panel">
      <h2 class="h6 mb-3">The three logins</h2>
      <dl class="small mb-0">
        <dt><i class="bi bi-house-door"></i> Homeowner</dt><dd>Residents and clients sign themselves up at <a href="/register">/register</a> and sign in at <a href="/login">/login</a>.</dd>
        <dt><i class="bi bi-building"></i> Admin</dt><dd>Company office: full control, at <a href="/admin/login">/admin/login</a>.</dd>
        <dt><i class="bi bi-shield-shaded"></i> Security company</dt><dd class="mb-2">Control room and officers, at <a href="/security/login">/security/login</a>.</dd>
      </dl>
      <a href="/admin/staff" class="btn btn-sm btn-outline-dark">Manage admin & security logins</a>
    </div>
  </div>
</div>
