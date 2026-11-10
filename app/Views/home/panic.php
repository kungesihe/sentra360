<?php $t = tenant(); $tel = preg_replace('/[^\d+]/', '', (string) $t['emergency_phone']); ?>
<div class="container py-4" style="max-width:720px">
  <?php require BASE_PATH . '/app/Views/partials/flash.php'; ?>
  <a href="/account" class="small"><i class="bi bi-arrow-left"></i> My account</a>
  <h1 class="h3 mt-2">Panic button</h1>

  <?php if ($active): ?>
    <a class="alert alert-danger d-block" href="/account/panic/<?= e($active['reference']) ?>"><strong>You already have an active alert (<?= e($active['reference']) ?>).</strong> Tap to follow it.</a>
  <?php endif; ?>

  <?php if (!$eligible): ?>
    <div class="panel text-center">
      <i class="bi bi-lock fs-2 text-secondary"></i>
      <p class="mt-2">The panic button is available to clients with an <strong>active protection service</strong> from <?= e($t['display_name']) ?>.</p>
      <a href="/account/assets/property/new" class="btn btn-brand">Apply for protection</a>
    </div>
  <?php else: ?>
    <div class="alert alert-warning small">
      <strong>Before you rely on this:</strong> alerts need an internet connection and this page open. They can't be sent if your phone is offline or the browser is closed. In a life-threatening emergency also call
      <?php if ($tel): ?><a href="tel:<?= e($tel) ?>" class="fw-bold"><?= e($t['emergency_phone']) ?></a> or <?php endif; ?><a href="tel:10111" class="fw-bold">10111</a>.
    </div>
    <div class="offline-banner alert alert-danger d-none" data-offline-banner><strong>You are offline.</strong> An alert cannot be sent right now. Call <?= $tel ? e($t['emergency_phone']) . ' or ' : '' ?>10111.</div>

    <form method="post" action="/account/panic" id="panic-form" data-panic-form>
      <?= csrf_field() ?>
      <input type="hidden" name="latitude" id="latitude"><input type="hidden" name="longitude" id="longitude">
      <input type="hidden" name="accuracy" id="accuracy"><input type="hidden" name="location_status" id="location_status" value="unavailable">

      <div class="text-center my-4">
        <button type="button" class="panic-btn" data-panic-hold aria-describedby="panic-help">
          <span class="panic-ring" data-panic-ring></span>
          <span class="panic-label">HOLD<br><small>3 seconds</small></span>
        </button>
        <div id="panic-help" class="small text-body-secondary mt-2" data-panic-status>Press and hold for 3 seconds to send an emergency alert.</div>
        <div class="small mt-1" data-geo-note>Your location will be requested when you press.</div>
      </div>

      <div class="panel">
        <div class="row g-3">
          <?php if ($properties): ?>
            <div class="col-md-6"><label class="form-label" for="property_id">Which property? <span class="text-body-secondary">(optional)</span></label>
              <select class="form-select" name="property_id" id="property_id"><option value="">Not at a property</option><?php foreach ($properties as $p): ?><option value="<?= (int) $p['id'] ?>"><?= e($p['name']) ?></option><?php endforeach; ?></select></div>
          <?php endif; ?>
          <?php if ($vehicles): ?>
            <div class="col-md-6"><label class="form-label" for="vehicle_id">In a vehicle? <span class="text-body-secondary">(optional)</span></label>
              <select class="form-select" name="vehicle_id" id="vehicle_id"><option value="">No</option><?php foreach ($vehicles as $v): ?><option value="<?= (int) $v['id'] ?>"><?= e($v['make'] . ' ' . $v['model'] . ' · ' . $v['registration']) ?></option><?php endforeach; ?></select></div>
          <?php endif; ?>
          <div class="col-12"><label class="form-label" for="extra_info">Extra information <span class="text-body-secondary">(optional, e.g. "intruder in the garden")</span></label><input class="form-control" name="extra_info" id="extra_info" maxlength="500"></div>
          <div class="col-sm-6"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" name="silent" id="silent" value="1" checked><label class="form-check-label" for="silent">Silent alert (no siren sound on this phone)</label></div></div>
          <div class="col-sm-6"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" name="notify_contacts" id="notify_contacts" value="1"<?= $contacts ? ' checked' : ' disabled' ?>><label class="form-check-label" for="notify_contacts">Also notify my emergency contacts<?= $contacts ? " ($contacts)" : ' (none saved)' ?></label></div></div>
        </div>
        <hr>
        <div class="form-check"><input class="form-check-input" type="checkbox" name="confirm" value="1" id="confirm_box" data-panic-confirm><label class="form-check-label small" for="confirm_box">Can't hold the button? Tick this box to confirm it's an emergency, then press <strong>Send alert</strong>.</label></div>
        <button type="submit" class="btn btn-danger mt-2" data-panic-submit>Send alert</button>
      </div>
    </form>
  <?php endif; ?>
</div>
