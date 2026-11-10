<form method="post" action="<?= $base ?>/incidents" enctype="multipart/form-data" class="panel" style="max-width:760px" novalidate>
  <?= csrf_field() ?>
  <p class="small text-body-secondary">Log an incident reported by phone, radio or observed on patrol.</p>
  <div class="row g-3">
    <div class="col-md-6"><label class="form-label" for="category_id">Category</label>
      <select class="form-select" id="category_id" name="category_id" required><option value="">Choose…</option><?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-12"><label class="form-label" for="location_text">Location</label>
      <div class="input-group"><input class="form-control" id="location_text" name="location_text" required value="<?= old('location_text') ?>"><button class="btn btn-outline-secondary" type="button" data-geo-fill data-geo-status="geo-status"><i class="bi bi-crosshair"></i> My location</button></div>
      <input type="hidden" name="latitude" id="latitude"><input type="hidden" name="longitude" id="longitude"><div id="geo-status" class="form-text"></div></div>
    <div class="col-12"><label class="form-label" for="description">Description</label><textarea class="form-control" id="description" name="description" rows="4" required><?= old('description') ?></textarea></div>
    <div class="col-12"><input class="form-control" type="file" name="files[]" multiple aria-label="Attachments" accept="image/png,image/jpeg,image/webp,application/pdf,video/mp4,video/webm,video/quicktime"></div>
    <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" id="assistance_required" name="assistance_required" value="1"><label class="form-check-label" for="assistance_required">Urgent</label></div></div>
  </div>
  <button class="btn btn-brand mt-3">Log incident</button>
</form>
