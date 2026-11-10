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
      <?php
      $P = App\Services\Plans::class;
      $protected = array_filter($assets, fn($a) => $a['protection_status'] === 'active');
      ?>
      <div class="panel protection-card h-100">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
          <div class="d-flex gap-3">
            <span class="protection-ico"><i class="bi bi-shield-check"></i></span>
            <div>
              <h2 class="h5 mb-1">My protection</h2>
              <p class="small text-body-secondary mb-0">
                <?php if (!$assets): ?>Pick a monthly plan for your home, car or business. Every plan includes 24/7 armed response and priority call-backs.
                <?php elseif (!$protected): ?>Nothing is protected yet. Choose a plan below and we'll send your quotation.
                <?php else: ?><?= count($protected) ?> of <?= count($assets) ?> protected.
                <?php endif; ?>
              </p>
            </div>
          </div>
          <a href="/account/services" class="btn btn-link btn-sm px-0">Prices</a>
        </div>
        <?php if ($assets): ?>
          <ul class="list-unstyled mb-2 mt-3 border-top pt-1">
            <?php foreach ($assets as $a):
              $type = $P::typeFor($a['kind'], $a);
              $active = $a['protection_status'] === 'active';
              $inProgress = !$active && $a['app_status'] && !in_array($a['app_status'], ['rejected', 'cancelled', 'active'], true); ?>
              <li class="d-flex flex-wrap justify-content-between align-items-center gap-2 py-2 border-bottom">
                <span><i class="bi bi-<?= e($P::DEFAULTS[$type]['icon']) ?> me-1"></i><?= e($a['label']) ?>
                  <span class="d-block small text-body-secondary"><?= e($plans[$type]['name'] ?? '') ?></span></span>
                <?php if ($active): ?><span class="badge text-bg-success"><i class="bi bi-shield-check"></i> Protected</span>
                <?php elseif ($inProgress): ?><a href="/account/applications" class="badge text-bg-info text-decoration-none"><?= e(App\Services\Ops::label($a['app_status'])) ?></a>
                <?php else: ?><a href="/account/assets/<?= $a['kind'] ?>" class="btn btn-sm btn-warning">Get protected</a><?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
        <div class="row g-2 mt-1">
          <?php foreach ($plans as $type => $plan): ?>
            <div class="col-sm-4">
              <a class="plan-mini" href="/account/assets/<?= $P::DEFAULTS[$type]['kind'] ?>/new<?= $type === 'business' ? '?type=business' : '' ?>">
                <i class="bi bi-<?= e($P::DEFAULTS[$type]['icon']) ?>"></i>
                <span class="fw-semibold">Add a <?= $type === 'vehicle' ? 'vehicle' : $type ?></span>
                <span class="small text-body-secondary"><?= strip_tags(App\Core\View::partial('shared/price', ['s' => $plan])) ?></span>
              </a>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3 mb-4">
    <?php foreach ([
      ['/account/incidents', 'megaphone', 'Incident reports', $counts['incidents']],
      ['/account/applications', 'house-check', 'Applications', $counts['applications']],
      ['/account/quotations', 'file-earmark-text', 'Quotations to review', $counts['quotations']],
      ['/account/requests', 'calendar-check', 'Open bookings', $counts['bookings']],
    ] as [$href, $ico, $label, $n]): ?>
      <div class="col-6 col-lg-3"><a href="<?= $href ?>" class="stat d-block text-decoration-none text-body"><div class="label"><i class="bi bi-<?= $ico ?>"></i> <?= $label ?></div><div class="value"><?= (int) $n ?></div></a></div>
    <?php endforeach; ?>
  </div>

  <h2 class="h5 mb-3">Quick actions</h2>
  <div class="row g-3 mb-4">
    <?php
    $wa = wa_number($t['whatsapp_number'] ?? null) ?: wa_number($t['emergency_phone'] ?? null);
    $actions = [
      ['/account/incidents/new', 'megaphone', 'Report an incident', 'Crime, suspicious activity, accidents. Goes straight to the control room.'],
    ];
    if ($wa) { $actions[] = ['https://wa.me/' . $wa, 'whatsapp', 'WhatsApp the control room', 'Message or call us on WhatsApp, 24/7.']; }
    $actions[] = ['/account/services', 'bag-check', 'Book a service', 'Escorts, events, CCTV, assessments and more'];
    $actions[] = ['/account/contacts', 'person-lines-fill', 'Emergency contacts', 'Who we call when you press panic'];
    foreach ($actions as [$href, $ico, $h, $p]): ?>
      <div class="col-sm-6 col-lg-3"><a class="action-card" href="<?= e($href) ?>"<?= str_starts_with($href, 'https://') ? ' target="_blank" rel="noopener"' : '' ?>><div class="ico"><i class="bi bi-<?= $ico ?>"></i></div><div class="fw-semibold"><?= $h ?></div><p class="small text-body-secondary mb-0"><?= $p ?></p></a></div>
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
