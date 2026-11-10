<?php $O = App\Services\Ops::class; ?>
<?php if ($ops['active_panics']): ?>
  <a href="/admin/incidents?status=open" class="alert alert-danger d-flex align-items-center gap-2 text-decoration-none"><i class="bi bi-broadcast-pin fs-4"></i><strong><?= $ops['active_panics'] ?> active panic alert<?= $ops['active_panics'] > 1 ? 's' : '' ?></strong> · open the incident list</a>
<?php endif; ?>
<div class="row g-3 mb-4">
  <?php foreach ([
    ['/admin/incidents?status=submitted', 'megaphone', 'New incident reports', $ops['new_incidents']],
    ['/admin/incidents?status=open', 'activity', 'Open incidents', $ops['open_incidents']],
    ['/admin/incidents?status=open', 'broadcast-pin', 'Active emergencies', $ops['active_panics']],
    ['/admin/reports', 'exclamation-triangle', 'Panic alerts (30 days)', $ops['panics_30']],
    ['/admin/applications?kind=property', 'house-check', 'Residential applications', $ops['prop_apps']],
    ['/admin/applications?kind=vehicle', 'car-front', 'Vehicle applications', $ops['veh_apps']],
    ['/admin/quotations?status=sent', 'file-earmark-text', 'Quotes awaiting acceptance', $ops['quotes_waiting']],
    ['/admin/requests', 'bag-check', 'Service requests', $ops['requests']],
    ['/admin/payments', 'cash-coin', 'Payments (30 days)', money((int) $ops['paid_30'])],
    ['/admin/homeowners', 'people', 'Homeowners', $stats['homeowners'] ?? 0],
    ['/admin/homeowners', 'shield-check', 'Active clients', $stats['clients'] ?? 0],
    ['/admin/applications?status=active', 'house-lock', 'Protected properties / vehicles', $ops['active_props'] . ' / ' . $ops['active_vehicles']],
  ] as [$href, $ico, $label, $val]): ?>
    <div class="col-6 col-md-4 col-xl-3"><a href="<?= $href ?>" class="stat d-block text-decoration-none text-body"><div class="label"><i class="bi bi-<?= $ico ?>"></i> <?= $label ?></div><div class="value"><?= e($val) ?></div></a></div>
  <?php endforeach; ?>
</div>
<div class="row g-4">
  <div class="col-lg-7">
    <div class="table-card">
      <div class="d-flex justify-content-between align-items-center p-3 border-bottom"><h2 class="h6 mb-0">Latest incidents</h2><a href="/admin/incidents" class="small">All incidents</a></div>
      <table class="table mb-0"><tbody>
        <?php foreach ($latestIncidents as $i): ?>
          <tr><td><a href="/admin/incidents/<?= (int) $i['id'] ?>" class="fw-semibold"><?= e($i['reference']) ?></a><div class="small text-body-secondary"><?= e($i['title']) ?></div></td><td><?= $O::badge($i['priority']) ?></td><td><?= $O::badge($i['status']) ?></td><td class="small text-body-secondary text-end"><?= e(date('j M H:i', strtotime($i['created_at']))) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$latestIncidents): ?><tr><td class="p-3 text-body-secondary">No incidents reported yet.</td></tr><?php endif; ?>
      </tbody></table>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="table-card">
      <div class="d-flex justify-content-between align-items-center p-3 border-bottom"><h2 class="h6 mb-0">New homeowners</h2><a href="/admin/homeowners" class="small">View all</a></div>
      <table class="table mb-0"><tbody>
        <?php foreach ($recent as $r): ?><tr><td><a href="/admin/homeowners/<?= (int) $r['id'] ?>"><?= e($r['first_name'] . ' ' . $r['surname']) ?></a></td><td class="text-end small text-body-secondary"><?= e(date('j M, H:i', strtotime($r['created_at']))) ?></td></tr><?php endforeach; ?>
        <?php if (!$recent): ?><tr><td class="text-body-secondary p-3">No registrations yet. Share <strong><?= e(app_url('/register')) ?></strong>.</td></tr><?php endif; ?>
      </tbody></table>
    </div>
  </div>
</div>
