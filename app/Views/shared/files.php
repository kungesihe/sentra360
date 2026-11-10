<?php /** Attachment gallery. Needs $files. */ ?>
<?php if ($files): ?>
  <div class="d-flex flex-wrap gap-2">
    <?php foreach ($files as $f): ?>
      <?php if (str_starts_with($f['mime'], 'image/')): ?>
        <a href="/files/<?= (int) $f['id'] ?>" target="_blank" rel="noopener" class="file-thumb"><img src="/files/<?= (int) $f['id'] ?>" alt="<?= e($f['original_name']) ?>" loading="lazy"></a>
      <?php else: ?>
        <a href="/files/<?= (int) $f['id'] ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary"><i class="bi bi-<?= str_starts_with($f['mime'], 'video/') ? 'film' : 'file-earmark-pdf' ?>"></i> <?= e($f['original_name']) ?> <span class="text-body-secondary">(<?= round($f['size_bytes'] / 1048576, 1) ?> MB)</span></a>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <p class="small text-body-secondary mb-0">No attachments.</p>
<?php endif; ?>
