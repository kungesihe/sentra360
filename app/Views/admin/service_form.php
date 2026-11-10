<?php $o = $_SESSION['_old'] ?? []; $v = fn($k, $d = '') => e($o[$k] ?? ($s[$k] ?? $d)); $pricing = $o['pricing'] ?? ($s['pricing'] ?? 'quote'); ?>
<a href="/admin/pricelist" class="small"><i class="bi bi-arrow-left"></i> Services &amp; prices</a>
<form method="post" action="/admin/services/<?= $s ? (int) $s['id'] : 'new' ?>" enctype="multipart/form-data" class="panel mt-2" style="max-width:860px" novalidate>
  <?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-md-7"><label class="form-label" for="name">Name</label><input class="form-control" id="name" name="name" required value="<?= $v('name') ?>"></div>
    <div class="col-md-5"><label class="form-label" for="category">Category</label><input class="form-control" id="category" name="category" required value="<?= $v('category', 'Specialised') ?>" list="cats"><datalist id="cats"><option>Residential</option><option>Specialised</option><option>Events</option><option>Business</option><option>Installations</option><option>Assessments</option></datalist></div>
    <div class="col-12"><label class="form-label" for="description">Description</label><textarea class="form-control" id="description" name="description" rows="4" required><?= $v('description') ?></textarea></div>
    <div class="col-md-4"><label class="form-label" for="pricing">Pricing</label><select class="form-select" id="pricing" name="pricing"><?php foreach (['quote' => 'Quotation required', 'fixed' => 'Fixed price', 'from' => 'From (starting price)'] as $k => $l): ?><option value="<?= $k ?>"<?= $pricing === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
    <div class="col-md-4"><label class="form-label" for="price">Price (R)</label><input class="form-control" id="price" name="price" inputmode="decimal" value="<?= e($o['price'] ?? (isset($s['price_cents']) && $s['price_cents'] !== null ? number_format($s['price_cents'] / 100, 2, '.', '') : '')) ?>"></div>
    <?php if ($s && $s['plan_for']): ?>
      <div class="col-12"><div class="alert alert-info small mb-0"><i class="bi bi-shield-check"></i> This is the <strong><?= e($s['plan_for']) ?></strong> protection plan. Customers sign up for it from their protection list; it is not booked in the services marketplace.</div></div>
      <div class="col-md-4"><label class="form-label" for="setup">Once-off setup fee (R)</label><input class="form-control" id="setup" name="setup" inputmode="decimal" value="<?= $s['setup_cents'] ? e(number_format($s['setup_cents'] / 100, 2, '.', '')) : '' ?>" placeholder="0.00"><div class="form-text">Tracker fitting or alarm installation. Empty = none.</div></div>
      <div class="col-md-8"><label class="form-label" for="includes">What's included (one per line)</label><textarea class="form-control" id="includes" name="includes" rows="4"><?= e($s['includes'] ?? '') ?></textarea></div>
    <?php endif; ?>
    <div class="col-md-4"><label class="form-label" for="price_unit">Unit</label><input class="form-control" id="price_unit" name="price_unit" value="<?= $v('price_unit') ?>" placeholder="per hour, per event…"></div>
    <div class="col-md-4"><label class="form-label" for="duration_text">Duration</label><input class="form-control" id="duration_text" name="duration_text" value="<?= $v('duration_text') ?>"></div>
    <div class="col-md-4"><label class="form-label" for="service_area">Service area</label><input class="form-control" id="service_area" name="service_area" value="<?= $v('service_area') ?>"></div>
    <div class="col-md-4"><label class="form-label" for="availability_text">Availability</label><input class="form-control" id="availability_text" name="availability_text" value="<?= $v('availability_text') ?>"></div>
    <div class="col-12"><label class="form-label" for="requirements">Additional requirements</label><textarea class="form-control" id="requirements" name="requirements" rows="2"><?= $v('requirements') ?></textarea></div>
    <div class="col-12"><label class="form-label" for="files">Photos (up to 4)</label><input class="form-control" type="file" id="files" name="files[]" multiple accept="image/png,image/jpeg,image/webp">
      <?php foreach ($photos as $ph): ?><label class="d-inline-flex align-items-center gap-1 me-3 mt-2 small"><img src="/files/<?= (int) $ph['id'] ?>" alt="" class="file-thumb-sm"><input type="checkbox" name="remove_photos[]" value="<?= (int) $ph['id'] ?>"> remove</label><?php endforeach; ?></div>
    <?php if (!$s || !$s['plan_for']): $on = explode(',', (string) ($s['addon_for'] ?? '')); ?>
      <div class="col-12"><span class="form-label d-block">Offer as an optional extra on</span>
        <?php foreach (['home' => 'Home plan', 'business' => 'Business plan', 'vehicle' => 'Vehicle plan'] as $k => $lbl): ?>
          <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="addon[]" value="<?= $k ?>" id="addon-<?= $k ?>"<?= in_array($k, $on, true) ? ' checked' : '' ?>><label class="form-check-label" for="addon-<?= $k ?>"><?= $lbl ?></label></div>
        <?php endforeach; ?>
        <div class="form-text">Only tick plans that don't already include it, so customers are never offered the same thing twice.</div>
      </div>
    <?php endif; ?>
    <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1"<?= ($s['is_active'] ?? 1) ? ' checked' : '' ?>><label class="form-check-label" for="is_active">Listed in the marketplace</label></div></div>
  </div>
  <button class="btn btn-brand mt-3">Save service</button>
</form>
