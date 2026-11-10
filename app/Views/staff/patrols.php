<div class="row g-4">
  <?php if ($canLog): ?>
  <div class="col-lg-4 order-lg-2">
    <form method="post" action="/security/patrols" class="panel" novalidate>
      <?= csrf_field() ?>
      <h2 class="h6">Patrol check-in</h2>
      <div class="mb-2"><label class="form-label" for="area">Area / street</label><input class="form-control" id="area" name="area" required value="<?= old('area') ?>"></div>
      <div class="mb-2"><label class="form-label" for="checkpoint">Checkpoint <span class="text-body-secondary">(optional)</span></label><input class="form-control" id="checkpoint" name="checkpoint" value="<?= old('checkpoint') ?>"></div>
      <div class="mb-2"><label class="form-label" for="notes">Notes</label><textarea class="form-control" id="notes" name="notes" rows="2"><?= old('notes') ?></textarea></div>
      <input type="hidden" name="latitude" id="latitude"><input type="hidden" name="longitude" id="longitude">
      <button type="button" class="btn btn-sm btn-outline-secondary mb-2" data-geo-fill data-geo-status="geo-status"><i class="bi bi-crosshair"></i> Add my GPS position</button><div id="geo-status" class="form-text mb-2"></div>
      <button class="btn btn-brand w-100">Log check-in</button>
    </form>
  </div>
  <?php endif; ?>
  <div class="<?= $canLog ? 'col-lg-8' : 'col-12' ?>">
    <div class="table-card"><div class="table-responsive"><table class="table mb-0">
      <thead><tr><th>When</th><th>Officer</th><th>Area</th><th>Checkpoint</th><th>Notes</th><th>GPS</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr><td class="small text-nowrap"><?= e(date('j M H:i', strtotime($r['created_at']))) ?></td><td><?= e($r['first_name'] . ' ' . $r['surname']) ?></td><td><?= e($r['area']) ?></td><td><?= e($r['checkpoint']) ?></td><td class="small"><?= e($r['notes']) ?></td>
            <td><?php if ($r['latitude']): ?><a href="https://www.openstreetmap.org/?mlat=<?= e($r['latitude']) ?>&mlon=<?= e($r['longitude']) ?>#map=17/<?= e($r['latitude']) ?>/<?= e($r['longitude']) ?>" target="_blank" rel="noopener"><i class="bi bi-geo-alt"></i></a><?php endif; ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="6" class="p-4 text-body-secondary">No patrol check-ins yet.</td></tr><?php endif; ?>
      </tbody>
    </table></div></div>
  </div>
</div>
