<?php /** Horizontal progress steps. Needs $steps (status codes in order) and $current. */
$idx = array_search($current, $steps, true);
$terminal = in_array($current, ['rejected', 'cancelled'], true);
if ($idx === false && in_array($current, ['closed', 'completed'], true)) {
    $idx = count($steps) - 1;
}
?>
<?php if ($terminal): ?>
  <div class="alert alert-secondary py-2 small mb-4"><?= App\Services\Ops::badge($current) ?> No further steps.</div>
<?php else: ?>
  <ol class="progress-steps mb-4">
    <?php foreach ($steps as $i => $s): $state = $idx === false ? '' : ($i < $idx ? 'done' : ($i === $idx ? 'current' : '')); ?>
      <li class="<?= $state ?>"><span class="dot"><?= $state === 'done' ? '<i class="bi bi-check"></i>' : $i + 1 ?></span><span class="lbl"><?= e(App\Services\Ops::label($s)) ?></span></li>
    <?php endforeach; ?>
  </ol>
<?php endif; ?>
