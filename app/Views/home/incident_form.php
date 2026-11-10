<?php $o = $_SESSION['_old'] ?? []; $sel = (string) ($o['category_id'] ?? ''); ?>
<div class="container py-4" style="max-width:900px">
  <?= App\Core\View::partial('home/_nav', ['active' => '/account/incidents']) ?>
  <h1 class="h4 mb-1"><?= e($title) ?></h1>
  <p class="text-body-secondary">Your report goes to <?= e(tenant()['display_name']) ?>. <strong>A report is not a dispatch.</strong> You'll see when it is received and whether a response is sent. In a life-threatening emergency call <strong>10111</strong> (SAPS) or <strong>10177</strong> (ambulance/fire)<?= tenant()['emergency_phone'] ? ', or ' . e(tenant()['emergency_phone']) : '' ?>.</p>
  <?php require BASE_PATH . '/app/Views/partials/flash.php'; ?>
  <form method="post" action="/account/incidents" enctype="multipart/form-data" class="panel" novalidate>
    <?= csrf_field() ?>
    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label" for="category_id">What happened?</label>
        <select class="form-select" id="category_id" name="category_id" required>
          <option value="">Choose…</option>
          <?php foreach (['crime' => 'Crime', 'emergency' => 'Accidents & emergencies'] as $g => $gl): ?>
            <optgroup label="<?= $gl ?>">
              <?php foreach ($categories as $c): if ($c['group_code'] !== $g) continue; ?>
                <option value="<?= (int) $c['id'] ?>"<?= $sel === (string) $c['id'] ? ' selected' : '' ?>><?= e($c['name']) ?></option>
              <?php endforeach; ?>
            </optgroup>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-3"><label class="form-label" for="occurred_date">Date</label><input class="form-control" type="date" id="occurred_date" name="occurred_date" required max="<?= date('Y-m-d') ?>" value="<?= old('occurred_date', date('Y-m-d')) ?>"></div>
      <div class="col-6 col-md-3"><label class="form-label" for="occurred_time">Time</label><input class="form-control" type="time" id="occurred_time" name="occurred_time" required value="<?= old('occurred_time', date('H:i')) ?>"></div>
      <div class="col-12">
        <label class="form-label" for="location_text">Where? (address or landmark)</label>
        <div class="input-group">
          <input class="form-control" id="location_text" name="location_text" required maxlength="255" value="<?= old('location_text') ?>" placeholder="e.g. Corner of Rivonia Rd & 5th Ave, Sandton">
          <button class="btn btn-outline-secondary" type="button" data-geo-fill data-geo-status="geo-status"><i class="bi bi-crosshair"></i> Use my location</button>
        </div>
        <input type="hidden" name="latitude" id="latitude" value="<?= old('latitude') ?>"><input type="hidden" name="longitude" id="longitude" value="<?= old('longitude') ?>">
        <div id="geo-status" class="form-text">GPS is optional. Your location is only shared with the company's authorised staff.</div>
      </div>
      <?php if ($properties): ?>
        <div class="col-md-6"><label class="form-label" for="property_id">At one of your properties? <span class="text-body-secondary">(optional)</span></label>
          <select class="form-select" id="property_id" name="property_id"><option value="">No</option><?php foreach ($properties as $p): ?><option value="<?= (int) $p['id'] ?>"><?= e($p['name'] . ' – ' . $p['address']) ?></option><?php endforeach; ?></select>
        </div>
      <?php endif; ?>
      <div class="col-12"><label class="form-label" for="description">Describe what happened</label><textarea class="form-control" id="description" name="description" rows="5" required maxlength="5000"><?= old('description') ?></textarea></div>
      <div class="col-md-6"><label class="form-label" for="witness_details">Witnesses <span class="text-body-secondary">(optional)</span></label><textarea class="form-control" id="witness_details" name="witness_details" rows="2" maxlength="2000"><?= old('witness_details') ?></textarea></div>
      <div class="col-md-6"><label class="form-label" for="injuries">Injuries <span class="text-body-secondary">(optional)</span></label><textarea class="form-control" id="injuries" name="injuries" rows="2" maxlength="2000"><?= old('injuries') ?></textarea></div>
      <div class="col-12"><label class="form-label" for="files">Photos, documents or short videos <span class="text-body-secondary">(up to 5 files, 5 MB each)</span></label><input class="form-control" type="file" id="files" name="files[]" multiple accept="image/png,image/jpeg,image/webp,application/pdf,video/mp4,video/webm,video/quicktime"></div>
      <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" id="assistance_required" name="assistance_required" value="1"<?= !empty($o['assistance_required']) ? ' checked' : '' ?>><label class="form-check-label" for="assistance_required"><strong>I need emergency assistance now</strong> (marks the report as urgent)</label></div></div>
    </div>
    <button class="btn btn-brand mt-4 px-4">Submit report</button>
  </form>
</div>
