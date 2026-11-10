<?php
/** Homeowner navigation: 5 sections, tabs inside each. */
$groups = nav_groups('homeowner');
$path = $active ?? parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$activeGroup = nav_active($groups, $path);
?>
<nav class="home-nav mb-2" aria-label="My account">
  <?php foreach ($groups as $i => [$href, $ico, $label]): $on = $i === $activeGroup; ?>
    <a href="<?= $href ?>" class="<?= $on ? 'active' : '' ?>"<?= $on ? ' aria-current="page"' : '' ?>><i class="bi bi-<?= $ico ?>"></i> <?= $label ?></a>
  <?php endforeach; ?>
</nav>
<?= App\Core\View::partial('partials/subtabs', ['groups' => $groups, 'activeGroup' => $activeGroup, 'path' => $path]) ?>
<div class="mb-3"></div>
