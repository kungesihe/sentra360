<?php
$t = tenant();
$tel = preg_replace('/[^\d+]/', '', (string) $t['emergency_phone']);
$open = !in_array($a['status'], ['resolved', 'cancelled'], true);
$isClient = App\Services\Incidents::isClient(App\Core\Auth::user());
$wa = wa_number($t['whatsapp_number'] ?? null) ?: wa_number($t['emergency_phone'] ?? null);
$steps = [
    ['submitted', 'Alert received by our server', $a['created_at']],
    ['delivered', 'Shown on the control room screen', $a['delivered_at']],
    ['acknowledged', 'Acknowledged by the control room', $a['acknowledged_at']],
    ['callback', 'Control room calling you back on WhatsApp', $a['callback_at']],
    ['dispatched', $isClient ? 'Armed response dispatched' : 'Response officer dispatched', $a['dispatched_at']],
    ['resolved', 'Resolved', $a['resolved_at']],
];
?>
<div class="container py-4" style="max-width:720px" data-panic-track="<?= e($a['reference']) ?>" data-panic-open="<?= $open ? '1' : '0' ?>" data-panic-silent="<?= $a['silent'] ? '1' : '0' ?>">
  <?php require BASE_PATH . '/app/Views/partials/flash.php'; ?>
  <div class="panic-status-head <?= $open ? 'open' : '' ?>">
    <div class="small text-uppercase fw-bold">Panic alert</div>
    <div class="display-6 fw-bold"><?= e($a['reference']) ?></div>
    <div data-panic-label class="fs-5"><?= e(App\Services\Ops::label($a['status'])) ?></div>
  </div>

  <ul class="list-group my-3" data-panic-steps>
    <?php foreach ($steps as [$code, $label, $at]): ?>
      <li class="list-group-item d-flex justify-content-between align-items-center" data-step="<?= $code ?>">
        <span><i class="bi <?= $at ? 'bi-check-circle-fill text-success' : 'bi-circle text-secondary' ?>" data-step-icon></i> <?= e($label) ?></span>
        <span class="small text-body-secondary" data-step-time><?= $at ? e(date('H:i:s', strtotime($at))) : 'waiting…' ?></span>
      </li>
    <?php endforeach; ?>
    <?php if ($a['status'] === 'cancelled'): ?><li class="list-group-item text-secondary">Cancelled by you at <?= e(date('H:i', strtotime($a['cancelled_at']))) ?></li><?php endif; ?>
  </ul>
  <div class="small mb-3" data-panic-officer></div>

  <div class="panel small mb-3">
    <div><strong>Location:</strong>
      <?= match ($a['location_status']) { 'captured' => 'GPS captured' . ($a['accuracy_m'] ? " (±{$a['accuracy_m']} m)" : ''), 'denied' => 'Location permission was refused, so no GPS was sent.', default => 'GPS was not available. Your registered property address is used if you selected one.' } ?>
      <?php if ($open): ?><span data-loc-updates class="text-body-secondary"></span><?php endif; ?>
    </div>
    <?php if ($contacts !== null && $contacts !== []): ?>
      <div class="mt-1"><strong>Emergency contacts:</strong> <?= in_array('sent', $contacts, true) ? 'notified by SMS.' : 'NOT notified, because SMS sending is not set up yet. Call them if you can.' ?></div>
    <?php endif; ?>
  </div>

  <?php if ($open): ?>
    <div class="panel small mb-3 d-flex flex-wrap align-items-center gap-2">
      <div class="flex-grow-1"><i class="bi bi-whatsapp text-success"></i> <strong>Keep your phone close.</strong> The control room will call you back on WhatsApp<?= $isClient ? ' while armed response is on the way' : '' ?>.</div>
      <?php if ($wa): ?><a class="btn btn-success btn-sm fw-bold" href="https://wa.me/<?= e($wa) ?>?text=<?= rawurlencode('Panic alert ' . $a['reference'] . ': I need help.') ?>" target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i> WhatsApp the control room</a><?php endif; ?>
    </div>
    <div class="alert alert-warning small">If this does not show <strong>Acknowledged</strong> within 2 minutes, call <?php if ($tel): ?><a class="fw-bold" href="tel:<?= e($tel) ?>"><?= e($t['emergency_phone']) ?></a> or <?php endif; ?><a class="fw-bold" href="tel:10111">10111</a>. Keep this page open so your location keeps updating.</div>
    <form method="post" action="/account/panic/<?= e($a['reference']) ?>/cancel" data-confirm="Cancel this alert? Only do this if you are safe or it was a mistake.">
      <?= csrf_field() ?>
      <div class="input-group"><input class="form-control" name="reason" placeholder="Reason (e.g. false alarm)" maxlength="200"><button class="btn btn-outline-secondary">I'm safe, cancel alert</button></div>
    </form>
  <?php endif; ?>
  <a href="/account" class="btn btn-link px-0 mt-3">Back to my account</a>
</div>
