<div class="container py-4" style="max-width:960px">
  <div class="alert alert-warning"><strong>Developer outbox — development mode only.</strong> These messages were recorded but <u>not delivered</u>, because no email or SMS provider is configured. This page does not exist in production.</div>
  <h1 class="h4 mb-3">Undelivered messages <?= tenant() ? 'for ' . e(tenant()['display_name']) : '(platform)' ?></h1>
  <?php foreach ($rows as $r): ?>
    <div class="panel mb-3">
      <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
        <div><span class="badge text-bg-dark text-uppercase"><?= e($r['channel']) ?></span> to <strong><?= e($r['recipient']) ?></strong></div>
        <div class="small"><?= status_badge($r['status']) ?> <span class="text-body-secondary"><?= e($r['created_at']) ?></span></div>
      </div>
      <?php if ($r['subject']): ?><div class="fw-semibold mb-1"><?= e($r['subject']) ?></div><?php endif; ?>
      <pre class="mb-0 small" style="white-space:pre-wrap"><?= preg_replace('#(https?://[^\s<]+)#', '<a href="$1">$1</a>', e($r['body'])) ?></pre>
    </div>
  <?php endforeach; ?>
  <?php if (!$rows): ?><p class="text-body-secondary">No messages yet.</p><?php endif; ?>
</div>
