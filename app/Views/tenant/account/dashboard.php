<?php $t = tenant(); ?>
<div class="container py-4">
  <?= App\Core\View::partial('home/_nav', ['active' => '/account']) ?>
  <?php require BASE_PATH . '/app/Views/partials/flash.php'; ?>
  <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
    <div>
      <div class="text-body-secondary small">My account</div>
      <h1 class="h3 mb-0">Hello, <?= e($user['first_name']) ?></h1>
    </div>
    <div class="d-flex gap-2 align-items-center"><?= status_badge($user['client_status']) ?></div>
  </div>

  <?php if ($verify && !($user['email_verified_at'] && $user['mobile_verified_at'])): ?>
    <div class="alert alert-warning d-flex flex-wrap align-items-center gap-3">
      <i class="bi bi-shield-exclamation fs-4"></i>
      <div class="flex-grow-1"><strong>Verify your account.</strong> Confirm your mobile number and email.</div>
      <a href="/account/verify" class="btn btn-sm btn-dark">Verify now</a>
    </div>
  <?php endif; ?>

  <?php foreach ($alerts as $al): ?>
    <div class="alert alert-<?= $al['level'] === 'urgent' ? 'danger' : ($al['level'] === 'warning' ? 'warning' : 'info') ?>">
      <i class="bi bi-megaphone-fill"></i> <strong><?= e($al['title']) ?></strong><?= $al['area'] ? ' · ' . e($al['area']) : '' ?>
      <div class="small mt-1" style="white-space:pre-line"><?= e($al['body']) ?></div>
    </div>
  <?php endforeach; ?>

  <?php if ($activePanic): ?>
    <a href="/account/panic/<?= e($activePanic['reference']) ?>" class="alert alert-danger d-flex align-items-center gap-3 text-decoration-none">
      <i class="bi bi-broadcast-pin fs-3"></i><div><strong>Active panic alert <?= e($activePanic['reference']) ?></strong><br><span class="small">Status: <?= e(App\Services\Ops::label($activePanic['status'])) ?>. Tap to follow it.</span></div>
    </a>
  <?php endif; ?>

  <div class="row g-4 mb-4">
    <div class="col-lg-4">
      <?php if ($panicEligible): ?>
        <a href="/account/panic" class="panic-tile">
          <span class="panic-tile-btn"><i class="bi bi-exclamation-triangle-fill"></i></span>
          <span class="fw-bold fs-5 mt-2">Panic button</span>
          <span class="small">Opens the emergency screen</span>
        </a>
      <?php else: ?>
        <div class="panel h-100">
          <div class="d-flex align-items-center gap-2 mb-2"><i class="bi bi-broadcast-pin fs-4 text-danger"></i><span class="fw-semibold">Unlock your panic button</span></div>
          <p class="small text-body-secondary">For your safety, the control room needs to know where you live and who to call. Finish these steps:</p>
          <ul class="list-unstyled small mb-0">
            <?php foreach ($gaps as [, $what, $href]): ?><li class="mb-2"><a href="<?= e($href) ?>"><i class="bi bi-circle"></i> <?= e($what) ?></a></li><?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>
    </div>
    <div class="col-lg-8">
      <div class="row g-3">
        <?php foreach ([
          ['/account/incidents', 'megaphone', 'Incident reports', $counts['incidents']],
          ['/account/applications', 'house-check', 'Applications', $counts['applications']],
          ['/account/quotations', 'file-earmark-text', 'Quotations to review', $counts['quotations']],
          ['/account/requests', 'calendar-check', 'Open bookings', $counts['bookings']],
        ] as [$href, $ico, $label, $n]): ?>
          <div class="col-6"><a href="<?= $href ?>" class="stat d-block text-decoration-none text-body"><div class="label"><i class="bi bi-<?= $ico ?>"></i> <?= $label ?></div><div class="value"><?= (int) $n ?></div></a></div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <h2 class="h5 mb-3">Quick actions</h2>
  <div class="row g-3 mb-4">
    <?php foreach ([
      ['/account/incidents/new', 'megaphone', 'Report an incident', 'Crime, suspicious activity, accidents, emergencies'],
      ['/account/assets/property/new', 'house-check', 'Protect your home', 'Register a property and apply for protection'],
      ['/account/assets/vehicle/new', 'car-front', 'Protect your vehicle', 'Register a vehicle and apply'],
      ['/account/services', 'bag-check', 'Book a service', 'Escorts, events, CCTV, assessments and more'],
    ] as [$href, $ico, $h, $p]): ?>
      <div class="col-sm-6 col-lg-3"><a class="action-card" href="<?= $href ?>"><div class="ico"><i class="bi bi-<?= $ico ?>"></i></div><div class="fw-semibold"><?= $h ?></div><p class="small text-body-secondary mb-0"><?= $p ?></p></a></div>
    <?php endforeach; ?>
  </div>

  <div class="row g-4">
    <div class="col-lg-6">
      <div class="table-card h-100">
        <div class="p-3 border-bottom d-flex justify-content-between"><h2 class="h6 mb-0">Recent reports</h2><a href="/account/incidents" class="small">All</a></div>
        <table class="table mb-0"><tbody>
          <?php foreach ($recentIncidents as $i): ?><tr><td><a href="/account/incidents/<?= (int) $i['id'] ?>"><?= e($i['reference']) ?></a><div class="small text-body-secondary"><?= e($i['title']) ?></div></td><td class="text-end"><?= App\Services\Ops::badge($i['status']) ?></td></tr><?php endforeach; ?>
          <?php if (!$recentIncidents): ?><tr><td class="text-body-secondary p-3">No reports yet.</td></tr><?php endif; ?>
        </tbody></table>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="table-card h-100">
        <div class="p-3 border-bottom d-flex justify-content-between"><h2 class="h6 mb-0">Applications & quotations</h2><a href="/account/applications" class="small">All</a></div>
        <table class="table mb-0"><tbody>
          <?php foreach ($pendingQuotes as $q): ?><tr class="table-warning"><td><a href="/account/quotations/<?= (int) $q['id'] ?>">Quotation <?= e($q['reference']) ?></a><div class="small">Waiting for your decision</div></td><td class="text-end fw-semibold"><?= money_exact((int) $q['total_cents']) ?></td></tr><?php endforeach; ?>
          <?php foreach ($applications as $a): ?><tr><td><a href="/account/applications/<?= e($a['kind']) ?>/<?= (int) $a['id'] ?>"><?= e($a['reference']) ?></a><div class="small text-body-secondary"><?= e($a['label']) ?></div></td><td class="text-end"><?= App\Services\Ops::badge($a['status']) ?></td></tr><?php endforeach; ?>
          <?php if (!$applications && !$pendingQuotes): ?><tr><td class="text-body-secondary p-3">No applications yet.</td></tr><?php endif; ?>
        </tbody></table>
      </div>
    </div>
  </div>
</div>
