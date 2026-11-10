<div class="row g-3 mb-4">
  <?php foreach ([
    ['Homeowners', $stats['homeowners'] ?? 0, 'house-door'],
    ['Verified', $stats['verified'] ?? 0, 'patch-check'],
    ['Active clients', $stats['clients'] ?? 0, 'shield-check'],
  ] as [$label, $val, $ico]): ?>
    <div class="col-sm-4"><div class="stat"><div class="label"><i class="bi bi-<?= $ico ?>"></i> <?= $label ?></div><div class="value"><?= (int) $val ?></div></div></div>
  <?php endforeach; ?>
</div>
<div class="row g-4">
  <div class="col-lg-7">
    <div class="panel h-100">
      <div class="d-flex align-items-center gap-2 mb-2"><span class="badge text-bg-secondary">Not live yet</span><h2 class="h6 mb-0">Live incident & panic alert feed</h2></div>
      <p class="text-body-secondary small">This is where the control room will see incoming incident reports and panic alerts, acknowledge them and assign officers. It arrives with incident reporting (Phase 2) and the panic button (Phase 4).</p>
      <div class="border rounded-3 p-4 text-center text-body-secondary small bg-body-tertiary"><i class="bi bi-broadcast fs-3 d-block mb-1"></i>No live feed in this version, so nothing here means "no alerts". In an emergency, use the company's emergency line.</div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="panel h-100">
      <h2 class="h6 mb-2">What you can do now</h2>
      <ul class="small mb-3">
        <li>Look up homeowners and their verified contact numbers.</li>
        <li>Change your password under "My login".</li>
      </ul>
      <a href="/security/homeowners" class="btn btn-brand btn-sm">Find a homeowner</a>
    </div>
  </div>
</div>
