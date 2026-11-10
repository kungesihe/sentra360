<?php $u = App\Core\Auth::user(); ?>
<div class="container py-4" style="max-width:1000px">
  <?php if ($u): ?><?= App\Core\View::partial('home/_nav', ['active' => '/account/services']) ?><?php endif; ?>
  <?php require BASE_PATH . '/app/Views/partials/flash.php'; ?>
  <a href="<?= $u ? '/account/services' : '/services' ?>" class="small"><i class="bi bi-arrow-left"></i> All services</a>
  <div class="row g-4 mt-1">
    <div class="col-lg-6">
      <span class="badge text-bg-light border mb-2"><?= e($s['category']) ?></span>
      <h1 class="h3"><?= e($s['name']) ?></h1>
      <div class="mb-3"><?= App\Core\View::partial('shared/price', ['s' => $s]) ?></div>
      <p style="white-space:pre-line"><?= e($s['description']) ?></p>
      <dl class="small">
        <?php if ($s['duration_text']): ?><dt>Duration</dt><dd><?= e($s['duration_text']) ?></dd><?php endif; ?>
        <?php if ($s['service_area']): ?><dt>Service area</dt><dd><?= e($s['service_area']) ?></dd><?php endif; ?>
        <?php if ($s['availability_text']): ?><dt>Availability</dt><dd><?= e($s['availability_text']) ?></dd><?php endif; ?>
        <?php if ($s['requirements']): ?><dt>Requirements</dt><dd style="white-space:pre-line"><?= e($s['requirements']) ?></dd><?php endif; ?>
      </dl>
      <?= App\Core\View::partial('shared/files', ['files' => $photos]) ?>
    </div>
    <div class="col-lg-6">
      <?php if (!$u): ?>
        <div class="panel"><p>Sign in or create a free account to request this service.</p><a href="/login" class="btn btn-brand">Sign in</a> <a href="/register" class="btn btn-outline-dark">Create account</a></div>
      <?php elseif ($u['portal'] !== 'homeowner'): ?>
        <div class="panel small text-body-secondary">Service requests are made by homeowners.</div>
      <?php else: ?>
        <form method="post" action="/account/services/<?= (int) $s['id'] ?>/request" class="panel" novalidate>
          <?= csrf_field() ?>
          <h2 class="h5 mb-3">Request this service</h2>
          <div class="row g-3">
            <div class="col-6"><label class="form-label" for="requested_date">Date</label><input class="form-control" type="date" id="requested_date" name="requested_date" min="<?= date('Y-m-d') ?>" required value="<?= old('requested_date') ?>"></div>
            <div class="col-6"><label class="form-label" for="requested_time">Time</label><input class="form-control" type="time" id="requested_time" name="requested_time" value="<?= old('requested_time') ?>"></div>
            <div class="col-12"><label class="form-label" for="location_text">Location</label><input class="form-control" id="location_text" name="location_text" required maxlength="255" value="<?= old('location_text') ?>"></div>
            <div class="col-12"><label class="form-label" for="duration_text">Duration</label><input class="form-control" id="duration_text" name="duration_text" maxlength="100" placeholder="e.g. 4 hours, 18:00–23:00" value="<?= old('duration_text') ?>"></div>
            <div class="col-12"><label class="form-label" for="instructions">Instructions</label><textarea class="form-control" id="instructions" name="instructions" rows="3" maxlength="3000"><?= old('instructions') ?></textarea></div>
          </div>
          <button class="btn btn-brand mt-3">Send request</button>
          <p class="small text-body-secondary mt-2 mb-0">This is a request, not a confirmed booking. You'll receive a quotation or confirmation.</p>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>
