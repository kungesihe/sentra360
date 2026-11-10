<div class="table-card">
  <div class="table-responsive">
    <table class="table table-sm">
      <thead><tr><th>When</th><th>Who</th><th>Action</th><th>Record</th><th>IP</th></tr></thead>
      <tbody>
      <?php foreach ($logs as $l): ?>
        <tr>
          <td class="small text-nowrap"><?= e($l['created_at']) ?></td>
          <td class="small"><?= $l['first_name'] ? e($l['first_name'] . ' ' . $l['surname']) : e(ucfirst($l['actor_type'])) ?></td>
          <td><code><?= e($l['action']) ?></code></td>
          <td class="small"><?= e(trim(($l['entity_type'] ?? '') . ' ' . ($l['entity_id'] ?? ''))) ?></td>
          <td class="small text-body-secondary"><?= e($l['ip_address'] ?? '') ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$logs): ?><tr><td colspan="5" class="p-4 text-body-secondary">No activity recorded yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
