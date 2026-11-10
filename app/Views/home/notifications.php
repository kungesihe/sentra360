<?php $home = ($user['portal'] ?? '') === 'homeowner'; ?>
<div class="<?= $home ? 'container py-4' : '' ?>">
  <?php if ($home): ?><?= App\Core\View::partial('home/_nav', []) ?><h1 class="h4 mb-3"><?= e($title) ?></h1><?php endif; ?>
  <div class="list-group">
    <?php foreach ($rows as $n): ?>
      <a href="<?= e($n['link'] ?: '#') ?>" class="list-group-item list-group-item-action <?= $n['read_at'] ? '' : 'fw-semibold' ?>">
        <div class="d-flex justify-content-between gap-2"><span><?= e($n['subject']) ?></span><span class="small text-body-secondary text-nowrap"><?= e(date('j M, H:i', strtotime($n['created_at']))) ?></span></div>
        <div class="small text-body-secondary fw-normal"><?= e($n['body']) ?></div>
      </a>
    <?php endforeach; ?>
    <?php if (!$rows): ?><div class="list-group-item text-body-secondary">No notifications.</div><?php endif; ?>
  </div>
</div>
