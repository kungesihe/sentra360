<?php $action = '/admin/homeowners/' . (int) $m['id'] . '/status'; ?>
<a href="/admin/homeowners" class="small"><i class="bi bi-arrow-left"></i> Homeowners</a>
<div class="row g-4 mt-1">
  <div class="col-lg-7"><?= App\Core\View::partial('shared/homeowner_details', ['m' => $m]) ?></div>
  <div class="col-lg-5">
    <div class="panel">
      <h2 class="h5 mb-3">Actions</h2>
      <?php if ($m['status'] === 'pending'): ?>
        <form method="post" action="<?= $action ?>" class="mb-3" data-confirm="Mark this homeowner as verified? Only do this if you have confirmed their identity and contact details another way.">
          <?= csrf_field() ?><input type="hidden" name="action" value="verify">
          <button class="btn btn-outline-success w-100">Manually verify</button>
          <div class="form-text">Use when you have confirmed their details in person or by phone.</div>
        </form>
      <?php endif; ?>
      <form method="post" action="<?= $action ?>" class="mb-3">
        <?= csrf_field() ?>
        <?php if ($m['client_status'] === 'client'): ?>
          <input type="hidden" name="action" value="remove_client"><button class="btn btn-outline-secondary w-100">Mark as community member (not a client)</button>
        <?php else: ?>
          <input type="hidden" name="action" value="make_client"><button class="btn btn-outline-primary w-100">Mark as paying client</button>
          <div class="form-text">From Phase 3 this happens automatically when a quotation is accepted and paid.</div>
        <?php endif; ?>
      </form>
      <?php if ($m['status'] !== 'suspended'): ?>
        <form method="post" action="<?= $action ?>" data-confirm="Suspend this homeowner? They will be signed out and unable to sign in.">
          <?= csrf_field() ?><input type="hidden" name="action" value="suspend">
          <label class="form-label small" for="note">Reason (internal)</label>
          <input class="form-control form-control-sm mb-2" id="note" name="note" maxlength="255">
          <button class="btn btn-outline-danger w-100">Suspend</button>
        </form>
      <?php else: ?>
        <form method="post" action="<?= $action ?>"><?= csrf_field() ?><input type="hidden" name="action" value="reactivate"><button class="btn btn-outline-primary w-100">Reactivate</button></form>
      <?php endif; ?>
    </div>
  </div>
</div>
