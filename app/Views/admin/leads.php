<p class="text-body-secondary">Community members who reported incidents or pressed the panic button in the last 90 days but don't have a protection plan yet. They already trust you with their safety, so they're your best potential clients.</p>
<div class="table-card"><div class="table-responsive"><table class="table table-hover mb-0">
  <thead><tr><th>Name</th><th>Panic alerts</th><th>Reports</th><th>Last activity</th><th>Application</th><th>Last offer</th><th></th></tr></thead>
  <tbody>
    <?php foreach ($rows as $r): $recent = $r['last_offer'] && strtotime($r['last_offer']) > strtotime('-14 days'); ?>
      <tr>
        <td><a href="/admin/homeowners/<?= (int) $r['id'] ?>" class="fw-semibold"><?= e($r['first_name'] . ' ' . $r['surname']) ?></a><div class="small text-body-secondary">member since <?= e(date('M Y', strtotime($r['created_at']))) ?></div></td>
        <td><?= (int) $r['panics'] ? '<span class="badge text-bg-danger">' . (int) $r['panics'] . '</span>' : '0' ?></td>
        <td><?= (int) $r['reports'] ?></td>
        <td class="small text-nowrap"><?= e($r['last_activity'] ? date('j M Y', strtotime($r['last_activity'])) : '—') ?></td>
        <td><?= (int) $r['open_apps'] ? '<span class="badge text-bg-info">in progress</span>' : '<span class="text-body-secondary small">none</span>' ?></td>
        <td class="small text-nowrap"><?= e($r['last_offer'] ? date('j M Y', strtotime($r['last_offer'])) : 'never') ?></td>
        <td class="text-end">
          <form method="post" action="/admin/leads/<?= (int) $r['id'] ?>/offer"><?= csrf_field() ?>
            <button class="btn btn-sm <?= $recent ? 'btn-outline-secondary' : 'btn-warning' ?>"<?= $recent ? ' disabled title="Sent in the last 14 days"' : '' ?>>Send offer</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="7" class="p-4 text-body-secondary">No leads yet. They appear here when community members report incidents.</td></tr><?php endif; ?>
  </tbody>
</table></div></div>
