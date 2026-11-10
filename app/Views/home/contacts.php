<div class="container py-4" style="max-width:900px">
  <?= App\Core\View::partial('home/_nav', []) ?>
  <?php require BASE_PATH . '/app/Views/partials/flash.php'; ?>
  <h1 class="h4"><?= e($title) ?></h1>
  <p class="text-body-secondary">People to notify when you press the panic button.<?php if (!$smsReady): ?> <span class="badge text-bg-warning">SMS sending is not set up yet, so contacts are recorded but won't be notified.</span><?php endif; ?></p>
  <div class="row g-4">
    <div class="col-lg-7">
      <div class="table-card">
        <table class="table mb-0">
          <thead><tr><th>Name</th><th>Mobile</th><th>Notify</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($rows as $c): ?>
            <tr><td><?= e($c['name']) ?><div class="small text-body-secondary"><?= e($c['relationship']) ?></div></td><td><?= e($c['mobile']) ?></td><td><?= $c['notify_on_panic'] ? 'Yes' : 'No' ?></td>
              <td class="text-end"><form method="post" action="/account/contacts/<?= (int) $c['id'] ?>/delete" data-confirm="Remove this contact?"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger" aria-label="Remove"><i class="bi bi-trash"></i></button></form></td></tr>
          <?php endforeach; ?>
          <?php if (!$rows): ?><tr><td colspan="4" class="p-3 text-body-secondary">No contacts yet.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <div class="col-lg-5">
      <form method="post" action="/account/contacts" class="panel" novalidate>
        <?= csrf_field() ?>
        <h2 class="h6">Add a contact</h2>
        <div class="mb-2"><label class="form-label" for="name">Name</label><input class="form-control" id="name" name="name" required value="<?= old('name') ?>"></div>
        <div class="mb-2"><label class="form-label" for="relationship">Relationship</label><input class="form-control" id="relationship" name="relationship" value="<?= old('relationship') ?>" placeholder="e.g. Spouse"></div>
        <div class="mb-2"><label class="form-label" for="mobile">Mobile</label><input class="form-control" type="tel" id="mobile" name="mobile" required value="<?= old('mobile') ?>"></div>
        <div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="notify_on_panic" name="notify_on_panic" value="1" checked><label class="form-check-label" for="notify_on_panic">Notify when I press the panic button</label></div>
        <button class="btn btn-brand">Save contact</button>
      </form>
    </div>
  </div>
</div>
