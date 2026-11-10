<?php
$O = App\Services\Ops::class;
$fmt = fn($s) => $s === null ? '—' : ($s < 120 ? round($s) . ' s' : round($s / 60, 1) . ' min');
$maxCat = max(1, ...array_map(fn($r) => (int) $r['n'], $byCategory ?: [['n' => 1]]));
$maxRev = max(1, ...array_map(fn($r) => (int) $r['cents'], $revenue ?: [['cents' => 1]]));
$conv = ($apps['total'] ?? 0) ? round(($apps['active'] / $apps['total']) * 100) : 0;
$quoteRate = ($quotes['sent'] ?? 0) ? round(($quotes['accepted'] / $quotes['sent']) * 100) : 0;
?>
<div class="d-flex flex-wrap justify-content-between gap-2 mb-3 no-print">
  <div class="btn-group" role="group" aria-label="Period"><?php foreach ([7, 30, 90, 365] as $d): ?><a href="/admin/reports?days=<?= $d ?>" class="btn btn-sm <?= $days === $d ? 'btn-dark' : 'btn-outline-secondary' ?>"><?= $d === 365 ? '12 months' : "$d days" ?></a><?php endforeach; ?></div>
  <div class="d-flex flex-wrap gap-2">
    <?php foreach (['incidents' => 'Incidents', 'applications' => 'Applications', 'payments' => 'Payments', 'homeowners' => 'Homeowners'] as $k => $l): ?><a class="btn btn-sm btn-outline-secondary" href="/admin/reports/export/<?= $k ?>"><i class="bi bi-file-earmark-spreadsheet"></i> <?= $l ?> (Excel)</a><?php endforeach; ?>
    <button type="button" class="btn btn-sm btn-outline-secondary" data-print><i class="bi bi-file-earmark-pdf"></i> Save as PDF</button>
  </div>
</div>
<div class="print-only h5 mb-3"><?= e(tenant()['display_name']) ?> · report for the last <?= $days ?> days · <?= date('j M Y') ?></div>
<div class="row g-3 mb-4">
  <?php foreach ([
    ['Panic alerts', (int) ($response['n'] ?? 0)], ['Avg. time to acknowledge', $fmt($response['ack_s'] !== null ? (float) $response['ack_s'] : null)],
    ['Avg. time to dispatch', $fmt($response['dispatch_s'] !== null ? (float) $response['dispatch_s'] : null)], ['Avg. officer arrival', $fmt($arrival !== null ? (float) $arrival : null)],
    ['Application conversion', $conv . '% (' . (int) $apps['active'] . '/' . (int) $apps['total'] . ')'], ['Quotes accepted', $quoteRate . '% (' . (int) $quotes['accepted'] . '/' . (int) $quotes['sent'] . ')'],
    ['New homeowners', (int) $growth['recent'] . ' (total ' . (int) $growth['total'] . ', clients ' . (int) $growth['clients'] . ')'], ['Patrol check-ins', $patrols],
  ] as [$l, $v]): ?>
    <div class="col-6 col-lg-3"><div class="stat"><div class="label"><?= $l ?></div><div class="value fs-5"><?= e($v) ?></div></div></div>
  <?php endforeach; ?>
</div>
<div class="row g-4">
  <div class="col-lg-6">
    <div class="panel h-100">
      <h2 class="h6 mb-3">Incidents by category</h2>
      <?php foreach ($byCategory as $c): ?>
        <div class="d-flex justify-content-between small"><span><?= e($c['name']) ?></span><strong><?= (int) $c['n'] ?></strong></div>
        <div class="progress mb-2" style="height:8px" role="progressbar" aria-label="<?= e($c['name']) ?>" aria-valuenow="<?= (int) $c['n'] ?>" aria-valuemin="0" aria-valuemax="<?= $maxCat ?>"><div class="progress-bar" style="width:<?= round($c['n'] / $maxCat * 100) ?>%;background:var(--brand)"></div></div>
      <?php endforeach; ?>
      <?php if (!$byCategory): ?><p class="small text-body-secondary">No incidents in this period.</p><?php endif; ?>
      <div class="mt-3"><?php foreach ($byStatus as $s): ?><span class="me-2"><?= $O::badge($s['status']) ?> <?= (int) $s['n'] ?></span><?php endforeach; ?></div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="panel h-100">
      <h2 class="h6 mb-3">Revenue received (last 12 months)</h2>
      <?php foreach ($revenue as $r): ?>
        <div class="d-flex justify-content-between small"><span><?= e(date('M Y', strtotime($r['month'] . '-01'))) ?></span><strong><?= money_exact((int) $r['cents']) ?></strong></div>
        <div class="progress mb-2" style="height:8px" role="progressbar" aria-label="<?= e($r['month']) ?>" aria-valuenow="<?= (int) $r['cents'] ?>" aria-valuemin="0" aria-valuemax="<?= $maxRev ?>"><div class="progress-bar bg-success" style="width:<?= round($r['cents'] / $maxRev * 100) ?>%"></div></div>
      <?php endforeach; ?>
      <?php if (!$revenue): ?><p class="small text-body-secondary">No payments recorded yet.</p><?php endif; ?>
    </div>
  </div>
  <div class="col-12">
    <div class="table-card">
      <div class="p-3 border-bottom"><h2 class="h6 mb-0">Service performance</h2></div>
      <table class="table mb-0"><thead><tr><th>Service</th><th>Requests</th><th>Booked</th></tr></thead><tbody>
        <?php foreach ($services as $s): ?><tr><td><?= e($s['name']) ?></td><td><?= (int) $s['n'] ?></td><td><?= (int) $s['booked'] ?></td></tr><?php endforeach; ?>
        <?php if (!$services): ?><tr><td colspan="3" class="text-body-secondary p-3">No service requests in this period.</td></tr><?php endif; ?>
      </tbody></table>
    </div>
  </div>
</div>
