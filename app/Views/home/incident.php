<div class="container py-4" style="max-width:1000px">
  <?= App\Core\View::partial('home/_nav', ['active' => '/account/incidents']) ?>
  <?php require BASE_PATH . '/app/Views/partials/flash.php'; ?>
  <a href="/account/incidents" class="small"><i class="bi bi-arrow-left"></i> My reports</a>
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mt-2 mb-3">
    <div><h1 class="h4 mb-0"><?= e($inc['title']) ?></h1><div class="text-body-secondary">Reference <strong><?= e($inc['reference']) ?></strong></div></div>
    <div class="text-end"><?= App\Services\Ops::badge($inc['status']) ?></div>
  </div>
  <?= App\Core\View::partial('shared/progress', ['steps' => ['submitted', 'received', 'assigned', 'in_progress', 'resolved'], 'current' => $inc['status']]) ?>
  <div class="row g-4">
    <div class="col-lg-7">
      <div class="panel mb-4">
        <dl class="row small mb-0">
          <dt class="col-sm-4">When</dt><dd class="col-sm-8"><?= e(date('j M Y, H:i', strtotime($inc['occurred_at']))) ?></dd>
          <dt class="col-sm-4">Where</dt><dd class="col-sm-8"><?= e($inc['location_text']) ?><?= $inc['latitude'] ? ' <span class="text-body-secondary">(GPS attached)</span>' : '' ?></dd>
          <dt class="col-sm-4">Description</dt><dd class="col-sm-8" style="white-space:pre-line"><?= e($inc['description']) ?></dd>
          <?php if ($inc['witness_details']): ?><dt class="col-sm-4">Witnesses</dt><dd class="col-sm-8"><?= e($inc['witness_details']) ?></dd><?php endif; ?>
          <?php if ($inc['injuries']): ?><dt class="col-sm-4">Injuries</dt><dd class="col-sm-8"><?= e($inc['injuries']) ?></dd><?php endif; ?>
          <dt class="col-sm-4">Urgent help asked</dt><dd class="col-sm-8"><?= $inc['assistance_required'] ? 'Yes' : 'No' ?></dd>
          <?php if ($inc['resolution']): ?><dt class="col-sm-4">Outcome</dt><dd class="col-sm-8"><?= e($inc['resolution']) ?></dd><?php endif; ?>
        </dl>
      </div>
      <div class="panel mb-4"><h2 class="h6">Attachments</h2><?= App\Core\View::partial('shared/files', ['files' => $files]) ?></div>
      <?php if (!in_array($inc['status'], ['resolved', 'closed'], true)): ?>
        <form method="post" action="/account/incidents/<?= (int) $inc['id'] ?>/info" enctype="multipart/form-data" class="panel">
          <?= csrf_field() ?>
          <h2 class="h6">Add information</h2>
          <textarea class="form-control mb-2" name="note" rows="2" maxlength="2000" placeholder="Anything new the company should know"></textarea>
          <input class="form-control mb-2" type="file" name="files[]" multiple accept="image/png,image/jpeg,image/webp,application/pdf,video/mp4,video/webm,video/quicktime">
          <button class="btn btn-sm btn-brand">Add to report</button>
        </form>
      <?php endif; ?>
    </div>
    <div class="col-lg-5">
      <div class="panel">
        <h2 class="h6 mb-3">Updates</h2>
        <?php if ($assignments): ?><p class="small"><i class="bi bi-person-walking"></i> Response officer: <strong><?= e(end($assignments)['first_name']) ?></strong> · <?= e(App\Services\Ops::label(end($assignments)['status'])) ?></p><?php endif; ?>
        <?= App\Core\View::partial('shared/timeline', ['timeline' => $timeline]) ?>
      </div>
    </div>
  </div>
</div>
