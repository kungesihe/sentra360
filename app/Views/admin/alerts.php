<div class="row g-4">
  <div class="col-lg-5">
    <form method="post" action="/admin/alerts" class="panel" novalidate>
      <?= csrf_field() ?>
      <h2 class="h6">Publish a community safety alert</h2>
      <p class="small text-body-secondary">Shown on the public home page and every homeowner's dashboard until it expires.</p>
      <div class="mb-2"><label class="form-label" for="title">Title</label><input class="form-control" id="title" name="title" required maxlength="150" value="<?= old('title') ?>" placeholder="e.g. Suspicious vehicle in Rivonia"></div>
      <div class="mb-2"><label class="form-label" for="body">Message</label><textarea class="form-control" id="body" name="body" rows="4" required><?= old('body') ?></textarea></div>
      <div class="row g-2 mb-3">
        <div class="col-6"><label class="form-label" for="level">Level</label><select class="form-select" id="level" name="level"><option value="info">Information</option><option value="warning">Warning</option><option value="urgent">Urgent</option></select></div>
        <div class="col-6"><label class="form-label" for="days">Show for (days)</label><input class="form-control" type="number" id="days" name="days" min="1" max="60" value="7"></div>
        <div class="col-12"><label class="form-label" for="area">Area <span class="text-body-secondary">(optional)</span></label><input class="form-control" id="area" name="area" value="<?= old('area') ?>"></div>
      </div>
      <button class="btn btn-brand">Publish alert</button>
    </form>
  </div>
  <div class="col-lg-7">
    <div class="table-card"><table class="table mb-0">
      <thead><tr><th>Alert</th><th>Level</th><th>Until</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($rows as $a): $live = !$a['expires_at'] || strtotime($a['expires_at']) > time(); ?>
          <tr class="<?= $live ? '' : 'text-body-secondary' ?>"><td><strong><?= e($a['title']) ?></strong><div class="small"><?= e(mb_strimwidth($a['body'], 0, 120, '…')) ?></div></td><td><?= e($a['level']) ?></td><td class="small"><?= e($a['expires_at'] ? date('j M H:i', strtotime($a['expires_at'])) : '—') ?></td>
            <td><?php if ($live): ?><form method="post" action="/admin/alerts/<?= (int) $a['id'] ?>/end"><?= csrf_field() ?><button class="btn btn-sm btn-outline-secondary">End now</button></form><?php endif; ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="4" class="p-3 text-body-secondary">No alerts published.</td></tr><?php endif; ?>
      </tbody>
    </table></div>
  </div>
</div>
