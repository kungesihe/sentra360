<?php $isProp = $kind === 'property'; $o = $_SESSION['_old'] ?? []; $chosen = (array) ($o['extras'] ?? []); $asset ??= null; $presetType ??= null; $extras ??= [];
$isBiz = ($o['property_type'] ?? $asset['property_type'] ?? $presetType) === App\Services\Plans::BUSINESS_TYPE;
$v = fn(string $k, string $d = '') => $o ? old($k) : e((string) ($asset[$k] ?? $d)); ?>
<div class="container py-4" style="max-width:900px">
  <?= App\Core\View::partial('home/_nav', ['active' => '/account/assets/' . $kind]) ?>
  <h1 class="h4 mb-3"><?= e($title) ?></h1>
  <?php require BASE_PATH . '/app/Views/partials/flash.php'; ?>
  <form method="post" action="/account/assets/<?= $kind ?><?= $asset ? '/' . (int) $asset['id'] : '' ?>" enctype="multipart/form-data" novalidate>
    <?= csrf_field() ?>
    <div class="panel mb-4">
      <h2 class="h6 text-uppercase text-body-secondary mb-3"><?= $isProp ? ($isBiz ? 'Business details' : 'Home details') : 'Vehicle details' ?></h2>
      <div class="row g-3">
        <?php if ($isProp): ?>
          <div class="col-md-6"><label class="form-label" for="name"><?= $isBiz ? 'Business name' : 'Name for this place' ?></label><input class="form-control" id="name" name="name" required placeholder="<?= $isBiz ? 'e.g. Block H Tuck Shop' : 'e.g. Home' ?>" value="<?= $v('name', $isBiz ? '' : ($asset ? '' : 'Home')) ?>"></div>
          <div class="col-md-6"><label class="form-label" for="property_type">Type</label><select class="form-select" id="property_type" name="property_type" data-plan-switch><?php foreach ($types as $tp): ?><option<?= $v('property_type', (string) $presetType) === e($tp) ? ' selected' : '' ?>><?= e($tp) ?></option><?php endforeach; ?></select></div>
          <div class="col-12"><label class="form-label" for="address">Physical address</label><div class="input-group"><input class="form-control" id="address" name="address" required value="<?= $v('address') ?>"><button class="btn btn-outline-secondary" type="button" data-geo-fill data-geo-status="geo-status"><i class="bi bi-crosshair"></i> I'm there now</button></div>
            <input type="hidden" name="latitude" id="latitude"><input type="hidden" name="longitude" id="longitude"><div id="geo-status" class="form-text">Optional: capture GPS if you're at the property.</div></div>
          <div class="col-md-6"><label class="form-label" for="suburb">Suburb</label><input class="form-control" id="suburb" name="suburb" required placeholder="e.g. <?= e(area()['suburb_example']) ?>" value="<?= $v('suburb') ?>"></div>
          <div class="col-md-6"><label class="form-label" for="city">City</label><input class="form-control" id="city" name="city" required value="<?= $v('city', area()['city']) ?>"></div>
          <div class="col-md-6"><label class="form-label" for="existing_security">Existing security systems</label><textarea class="form-control" id="existing_security" name="existing_security" rows="2" placeholder="Alarm, beams, cameras, guard dog…"><?= $v('existing_security') ?></textarea></div>
          <div class="col-md-6"><label class="form-label" for="access_requirements">Access requirements</label><textarea class="form-control" id="access_requirements" name="access_requirements" rows="2" placeholder="Gate code process, estate access, dogs…"><?= $v('access_requirements') ?></textarea></div>
          <div class="col-md-6"><label class="form-label" for="security_concerns">Security concerns</label><textarea class="form-control" id="security_concerns" name="security_concerns" rows="2"><?= $v('security_concerns') ?></textarea></div>
          <div class="col-md-6"><label class="form-label" for="additional_info">Additional information</label><textarea class="form-control" id="additional_info" name="additional_info" rows="2"><?= $v('additional_info') ?></textarea></div>
        <?php else: ?>
          <div class="col-md-4"><label class="form-label" for="make">Make</label><input class="form-control" id="make" name="make" required value="<?= $v('make') ?>" placeholder="Toyota"></div>
          <div class="col-md-4"><label class="form-label" for="model">Model</label><input class="form-control" id="model" name="model" required value="<?= $v('model') ?>" placeholder="Corolla Cross"></div>
          <div class="col-md-4"><label class="form-label" for="year">Year</label><input class="form-control" id="year" name="year" inputmode="numeric" maxlength="4" value="<?= $v('year') ?>"></div>
          <div class="col-md-4"><label class="form-label" for="registration">Registration number</label><input class="form-control text-uppercase" id="registration" name="registration" required value="<?= $v('registration') ?>"></div>
          <div class="col-md-4"><label class="form-label" for="colour">Colour</label><input class="form-control" id="colour" name="colour" required value="<?= $v('colour') ?>"></div>
          <div class="col-md-4"><label class="form-label" for="vin">VIN <span class="text-body-secondary">(optional)</span></label><input class="form-control text-uppercase" id="vin" name="vin" maxlength="30" value="<?= $v('vin') ?>"></div>
          <div class="col-md-6"><label class="form-label" for="primary_driver">Primary driver</label><input class="form-control" id="primary_driver" name="primary_driver" value="<?= old('primary_driver', App\Core\Auth::user()['first_name'] . ' ' . App\Core\Auth::user()['surname']) ?>"></div>
          <div class="col-md-6"><label class="form-label" for="additional_info">Additional security information</label><input class="form-control" id="additional_info" name="additional_info" value="<?= $v('additional_info') ?>" placeholder="Tracker installed, immobiliser…"></div>
        <?php endif; ?>
        <?php if (!$asset): ?><div class="col-12"><label class="form-label" for="files">Photos <span class="text-body-secondary">(optional, up to 5)</span></label><input class="form-control" type="file" id="files" name="files[]" multiple accept="image/png,image/jpeg,image/webp"></div><?php endif; ?>
      </div>
    </div>
    <?php if (!$asset): ?>
    <div class="panel mb-4">
      <h2 class="h6 text-uppercase text-body-secondary mb-3">Your protection plan</h2>
      <div class="row g-3 mb-3">
        <?php foreach ($plans as $type => $plan): if (($type === 'vehicle') === $isProp) continue; ?>
          <div class="col-md-8<?= $isProp && ($type === 'business') !== $isBiz ? ' d-none' : '' ?>" data-plan-for="<?= e($type) ?>"><?= App\Core\View::partial('shared/plan_card', ['plan' => $plan]) ?></div>
        <?php endforeach; ?>
        <?php if (!$plans): ?><div class="col-12 small text-body-secondary">Plans are being set up. Save this now and we'll contact you.</div><?php endif; ?>
      </div>
      <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" role="switch" id="apply" name="apply" value="1"<?= !$o || !empty($o['apply']) ? ' checked' : '' ?>><label class="form-check-label fw-semibold" for="apply">Yes, sign me up for this plan</label></div>
      <?php if ($extras): ?>
        <div class="small fw-semibold mb-1">Optional extras</div>
        <div class="row g-1">
          <?php foreach ($extras as $i => $x): ?>
            <div class="col-sm-6"><div class="form-check small"><input class="form-check-input" type="checkbox" name="extras[]" value="<?= e($x['name']) ?>" id="x<?= $i ?>"<?= in_array($x['name'], $chosen, true) ? ' checked' : '' ?>><label class="form-check-label" for="x<?= $i ?>"><?= e($x['name']) ?> <span class="text-body-secondary">· <?= strip_tags(App\Core\View::partial('shared/price', ['s' => $x])) ?></span></label></div></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <textarea class="form-control mt-3" name="notes" rows="2" placeholder="Anything we should know (optional)"><?= old('notes') ?></textarea>
      <p class="small text-body-secondary mt-2 mb-0">We confirm your details<?= $isProp ? '' : ' and book the tracker fitting' ?>, send a quotation to accept in the app, and your protection starts once it's paid.</p>
    </div>
    <?php endif; ?>
    <button class="btn btn-brand px-4"><?= $asset ? 'Save changes' : 'Save' ?></button><?php if ($asset): ?> <a href="/account/assets/<?= $kind ?>" class="btn btn-link">Cancel</a><?php endif; ?>
  </form>
</div>
