<?php $O = App\Services\Ops::class; ?>
<div class="table-card"><div class="table-responsive"><table class="table table-hover mb-0">
  <thead><tr><th>Incident</th><th>Where</th><th>Priority</th><th>My status</th><th>Assigned</th></tr></thead>
  <tbody>
    <?php foreach ($rows as $r): $done = in_array($r['status'], ['completed', 'declined'], true); ?>
      <tr class="<?= $done ? 'text-body-secondary' : ($r['source'] === 'panic' ? 'table-danger' : '') ?>">
        <td><a class="fw-semibold" href="/security/incidents/<?= (int) $r['incident_id'] ?>"><?= e($r['reference']) ?></a><div class="small"><?= e($r['title']) ?></div></td>
        <td class="small"><?= e($r['location_text']) ?></td><td><?= $O::badge($r['priority']) ?></td><td><?= $O::badge($r['status']) ?></td>
        <td class="small text-nowrap"><?= e(date('j M H:i', strtotime($r['assigned_at']))) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="5" class="p-4 text-body-secondary">No assignments yet.</td></tr><?php endif; ?>
  </tbody>
</table></div></div>
