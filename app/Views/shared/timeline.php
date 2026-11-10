<?php /** Status/notes timeline. Needs $timeline. */ ?>
<ol class="timeline">
  <?php foreach ($timeline as $ev): ?>
    <li class="<?= $ev['is_internal'] ? 'internal' : '' ?>">
      <div class="d-flex flex-wrap justify-content-between gap-2">
        <div>
          <?php if ($ev['to_status'] && $ev['to_status'] !== $ev['from_status']): ?><?= App\Services\Ops::badge($ev['to_status']) ?><?php endif; ?>
          <?php if ($ev['is_internal']): ?><span class="badge text-bg-dark">internal note</span><?php endif; ?>
        </div>
        <span class="small text-body-secondary"><?= e(date('j M Y, H:i', strtotime($ev['created_at']))) ?><?= $ev['first_name'] ? ' · ' . e($ev['first_name'] . ' ' . mb_substr((string) $ev['surname'], 0, 1) . '.') : '' ?></span>
      </div>
      <?php if ($ev['note']): ?><div class="small mt-1" style="white-space:pre-line"><?= e($ev['note']) ?></div><?php endif; ?>
    </li>
  <?php endforeach; ?>
  <?php if (!$timeline): ?><li class="text-body-secondary small">No updates yet.</li><?php endif; ?>
</ol>
