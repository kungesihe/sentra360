<?php
$O = App\Services\Ops::class;
$open = !in_array($inc['status'], ['resolved', 'closed'], true);
$points = array_map(fn($p) => [(float) $p['latitude'], (float) $p['longitude']], $trail);
?>
<a href="<?= $base ?>/incidents" class="small"><i class="bi bi-arrow-left"></i> Incidents</a>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mt-2 mb-3">
  <div>
    <div class="h5 mb-0"><?= e($inc['category_name'] ?? $inc['title']) ?> <?= $inc['source'] === 'panic' ? '<span class="badge text-bg-danger">PANIC ALERT</span>' : '' ?></div>
    <div class="small text-body-secondary">Reported <?= e(date('j M Y H:i', strtotime($inc['created_at']))) ?> · occurred <?= e(date('j M Y H:i', strtotime($inc['occurred_at']))) ?></div>
  </div>
  <div class="d-flex gap-2"><?= $O::badge($inc['priority']) ?> <?= $O::badge($inc['status']) ?></div>
</div>

<?php if ($panic): ?>
  <div class="alert <?= in_array($panic['status'], ['resolved', 'cancelled'], true) ? 'alert-secondary' : 'alert-danger' ?> d-flex flex-wrap align-items-center gap-3">
    <div class="flex-grow-1"><strong>Panic <?= e($panic['reference']) ?></strong> · <?= e($O::label($panic['status'])) ?>
      <div class="small">Location: <?= e(['captured' => 'GPS captured' . ($panic['accuracy_m'] ? " (±{$panic['accuracy_m']} m)" : ''), 'denied' => 'client refused location permission', 'unavailable' => 'GPS unavailable'][$panic['location_status']]) ?> · <?= $panic['silent'] ? 'silent alert' : 'audible alert' ?><?= $panic['extra_info'] ? ' · "' . e($panic['extra_info']) . '"' : '' ?></div>
    </div>
    <?php if (in_array($panic['status'], ['submitted', 'delivered'], true)): ?>
      <form method="post" action="<?= $base ?>/incidents/<?= (int) $inc['id'] ?>/acknowledge"><?= csrf_field() ?><button class="btn btn-light fw-bold">Acknowledge</button></form>
    <?php endif; ?>
  </div>
<?php endif; ?>

<div class="row g-4">
  <div class="col-xl-7">
    <?php if ($inc['latitude'] || $points): ?>
      <div class="map-box mb-3" data-map data-lat="<?= e($inc['latitude']) ?>" data-lng="<?= e($inc['longitude']) ?>" data-label="<?= e($inc['reference']) ?>" data-trail="<?= e(json_encode($points)) ?>"></div>
      <div class="small mb-3"><a href="https://www.google.com/maps/dir/?api=1&destination=<?= e($inc['latitude'] . ',' . $inc['longitude']) ?>" target="_blank" rel="noopener"><i class="bi bi-sign-turn-right"></i> Directions</a></div>
    <?php endif; ?>
    <div class="panel mb-3">
      <dl class="row small mb-0">
        <dt class="col-sm-4">Location</dt><dd class="col-sm-8"><?= e($inc['location_text']) ?></dd>
        <?php if ($asset): ?><dt class="col-sm-4">Protected property</dt><dd class="col-sm-8"><?= e($asset['name'] . ', ' . $asset['address'] . ', ' . $asset['suburb'] . ', ' . $asset['city']) ?><?php if ($asset['access_requirements']): ?><div class="text-body-secondary">Access: <?= e($asset['access_requirements']) ?></div><?php endif; ?></dd><?php endif; ?>
        <?php if ($vehicle): ?><dt class="col-sm-4">Vehicle</dt><dd class="col-sm-8"><?= e("{$vehicle['colour']} {$vehicle['make']} {$vehicle['model']} · {$vehicle['registration']}") ?></dd><?php endif; ?>
        <dt class="col-sm-4">Description</dt><dd class="col-sm-8" style="white-space:pre-line"><?= e($inc['description']) ?></dd>
        <?php if ($inc['witness_details']): ?><dt class="col-sm-4">Witnesses</dt><dd class="col-sm-8"><?= e($inc['witness_details']) ?></dd><?php endif; ?>
        <?php if ($inc['injuries']): ?><dt class="col-sm-4">Injuries</dt><dd class="col-sm-8"><?= e($inc['injuries']) ?></dd><?php endif; ?>
        <dt class="col-sm-4">Urgent help asked</dt><dd class="col-sm-8"><?= $inc['assistance_required'] ? '<strong class="text-danger">Yes</strong>' : 'No' ?></dd>
        <?php if ($inc['first_name']): ?>
          <dt class="col-sm-4">Reporter</dt><dd class="col-sm-8"><?= e($inc['first_name'] . ' ' . $inc['surname']) ?> · <a href="tel:<?= e($inc['reporter_mobile']) ?>"><?= e($inc['reporter_mobile']) ?></a>
            <?= $reporterIsClient ? '<span class="badge text-bg-success">Client</span>' : '<span class="badge text-bg-warning">Community member: not a client</span>' ?></dd>
        <?php endif; ?>
        <?php foreach ($contacts as $c): ?><dt class="col-sm-4">Emergency contact</dt><dd class="col-sm-8"><?= e($c['name']) ?> (<?= e($c['relationship'] ?: 'contact') ?>) · <a href="tel:<?= e($c['mobile']) ?>"><?= e($c['mobile']) ?></a></dd><?php endforeach; ?>
        <?php if ($inc['resolution']): ?><dt class="col-sm-4">Outcome</dt><dd class="col-sm-8"><?= e($inc['resolution']) ?></dd><?php endif; ?>
      </dl>
    </div>
    <div class="panel mb-3"><h2 class="h6">Evidence & attachments</h2><?= App\Core\View::partial('shared/files', ['files' => $files]) ?></div>
    <div class="panel"><h2 class="h6 mb-3">Timeline</h2><?= App\Core\View::partial('shared/timeline', ['timeline' => $timeline]) ?></div>
  </div>

  <div class="col-xl-5">
    <?php if ($portal === 'admin' && $inc['reporter_id'] && $reporterIsClient === false): ?>
      <form method="post" action="/admin/leads/<?= (int) $inc['reporter_id'] ?>/offer" class="panel mb-3 border-warning">
        <?= csrf_field() ?>
        <h2 class="h6"><i class="bi bi-lightning-charge text-warning"></i> Lead: this person isn't a client yet</h2>
        <p class="small text-body-secondary mb-2">After helping, invite them to protect their home. They get the message in the app with a link to apply.<?= $lastOffer ? ' Last offer: ' . e(date('j M Y', strtotime($lastOffer))) . '.' : '' ?></p>
        <input type="hidden" name="return" value="/admin/incidents/<?= (int) $inc['id'] ?>">
        <button class="btn btn-sm btn-warning">Send protection offer</button>
      </form>
    <?php endif; ?>
    <?php if ($myAssignment): ?>
      <div class="panel mb-3 border-primary">
        <h2 class="h6">My response · <?= $O::badge($myAssignment['status']) ?></h2>
        <form method="post" action="/security/incidents/<?= (int) $inc['id'] ?>/response" class="d-flex flex-wrap gap-2">
          <?= csrf_field() ?>
          <input class="form-control form-control-sm w-100" name="note" placeholder="Note (optional)" maxlength="1000">
          <?php foreach (['accepted' => ['Accept', 'outline-primary'], 'en_route' => ['En route', 'primary'], 'on_scene' => ['Arrived on scene', 'success'], 'completed' => ['Response completed', 'dark'], 'declined' => ['Decline', 'outline-danger']] as $st => [$lbl, $cls]): ?>
            <button name="status" value="<?= $st ?>" class="btn btn-sm btn-<?= $cls ?>"><?= $lbl ?></button>
          <?php endforeach; ?>
        </form>
      </div>
    <?php endif; ?>

    <div class="panel mb-3">
      <h2 class="h6">Response team</h2>
      <?php foreach ($assignments as $a): ?>
        <div class="small d-flex justify-content-between border-bottom py-1"><span><i class="bi bi-person-walking"></i> <?= e($a['first_name'] . ' ' . $a['surname']) ?></span><span><?= $O::badge($a['status']) ?><?= $a['arrived_at'] ? ' · arrived ' . e(date('H:i', strtotime($a['arrived_at']))) : '' ?></span></div>
      <?php endforeach; ?>
      <?php if (!$assignments): ?><p class="small text-body-secondary">Nobody assigned yet. Nothing has been dispatched.</p><?php endif; ?>
      <?php if ($open): ?>
        <form method="post" action="<?= $base ?>/incidents/<?= (int) $inc['id'] ?>/assign" class="input-group input-group-sm mt-2">
          <?= csrf_field() ?>
          <select class="form-select" name="officer_id" aria-label="Officer" required><option value="">Assign an officer…</option><?php foreach ($officers as $o): ?><option value="<?= (int) $o['id'] ?>"><?= e($o['first_name'] . ' ' . $o['surname']) ?> (<?= (int) $o['open_jobs'] ?> open)</option><?php endforeach; ?></select>
          <button class="btn btn-brand">Dispatch</button>
        </form>
        <?php if (!$officers): ?><p class="small text-danger mt-1 mb-0">No active security logins. Create them under Admin → Admin & security logins.</p><?php endif; ?>
      <?php endif; ?>
    </div>

    <?php if ($open): ?>
      <form method="post" action="<?= $base ?>/incidents/<?= (int) $inc['id'] ?>/status" class="panel mb-3">
        <?= csrf_field() ?>
        <h2 class="h6">Update status</h2>
        <select class="form-select form-select-sm mb-2" name="status" aria-label="New status" data-resolution-toggle>
          <?php foreach (App\Services\Incidents::STATUSES as $s): ?><option value="<?= $s ?>"<?= $inc['status'] === $s ? ' selected' : '' ?>><?= e($O::label($s)) ?></option><?php endforeach; ?>
        </select>
        <textarea class="form-control form-control-sm mb-2" name="note" rows="2" placeholder="Message to the reporter (optional)"></textarea>
        <textarea class="form-control form-control-sm mb-2 d-none" name="resolution" rows="2" placeholder="Outcome / resolution (required to resolve)" data-resolution-field></textarea>
        <button class="btn btn-sm btn-outline-dark">Save status</button>
      </form>
    <?php endif; ?>

    <form method="post" action="<?= $base ?>/incidents/<?= (int) $inc['id'] ?>/note" enctype="multipart/form-data" class="panel">
      <?= csrf_field() ?>
      <h2 class="h6">Add note or photos</h2>
      <textarea class="form-control form-control-sm mb-2" name="note" rows="2" maxlength="2000"></textarea>
      <input class="form-control form-control-sm mb-2" type="file" name="files[]" multiple accept="image/png,image/jpeg,image/webp,application/pdf,video/mp4,video/webm,video/quicktime" aria-label="Photos">
      <div class="form-check form-check-inline small"><input class="form-check-input" type="checkbox" name="internal" value="1" id="internal" checked><label class="form-check-label" for="internal">Internal (staff only)</label></div>
      <button class="btn btn-sm btn-brand">Add</button>
    </form>
  </div>
</div>
