<?php
$u = App\Core\Auth::user();
$unread = $u ? App\Services\Ops::unreadCount((int) $u['id']) : 0;
$items = [
    ['/account', 'house', 'Home'],
    ['/account/incidents', 'megaphone', 'Reports'],
    ['/account/assets/property', 'house-check', 'Properties'],
    ['/account/assets/vehicle', 'car-front', 'Vehicles'],
    ['/account/applications', 'clipboard-check', 'Applications'],
    ['/account/services', 'bag-check', 'Services'],
    ['/account/requests', 'calendar-check', 'Bookings'],
    ['/account/quotations', 'file-earmark-text', 'Quotations'],
    ['/account/payments', 'credit-card', 'Payments'],
    ['/account/contacts', 'person-lines-fill', 'Emergency contacts'],
    ['/account/notifications', 'bell', 'Notifications'],
    ['/account/profile', 'person-gear', 'Profile'],
];
$active = $active ?? parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
?>
<nav class="home-nav mb-4" aria-label="My account">
  <?php foreach ($items as [$href, $ico, $label]):
    $on = $active === $href || ($href !== '/account' && str_starts_with($active, $href)); ?>
    <a href="<?= $href ?>" class="<?= $on ? 'active' : '' ?>"<?= $on ? ' aria-current="page"' : '' ?>><i class="bi bi-<?= $ico ?>"></i> <?= $label ?><?php if ($href === '/account/notifications' && $unread): ?> <span class="badge rounded-pill text-bg-danger"><?= $unread ?></span><?php endif; ?></a>
  <?php endforeach; ?>
</nav>
