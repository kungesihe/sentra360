<?php
/** Tabs for the current nav section. Needs $groups, $activeGroup, $path. */
$tabs = $activeGroup !== null ? $groups[$activeGroup][3] : [];
if (count($tabs) > 1):
    // Best matching tab for this path.
    $cur = null; $len = -1;
    foreach ($tabs as $i => [$h]) {
        if (($path === $h || str_starts_with($path, $h . '/')) && strlen($h) > $len) { $cur = $i; $len = strlen($h); }
    }
?>
  <nav class="subtabs" aria-label="<?= e($groups[$activeGroup][2]) ?>">
    <?php foreach ($tabs as $i => [$h, $label]): ?>
      <a href="<?= e($h) ?>" class="<?= $i === $cur ? 'active' : '' ?>"<?= $i === $cur ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
    <?php endforeach; ?>
  </nav>
<?php endif; ?>
