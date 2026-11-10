<?php
/** The one page for services and prices: monthly plans on top, once-off services below. */
$public = !App\Core\Auth::user();
$t = tenant();
$plans = App\Services\Plans::all();
?>
<div class="container py-4">
  <?php if (!$public): ?><?= App\Core\View::partial('home/_nav', []) ?><?php endif; ?>
  <?php require BASE_PATH . '/app/Views/partials/flash.php'; ?>
  <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
    <div>
      <h1 class="h4 mb-1">Services &amp; prices</h1>
      <p class="text-body-secondary mb-0"><?= e($t['display_name']) ?> · <?= e(area()['name']) ?> and surrounding areas. Prices exclude VAT (15%).</p>
    </div>
    <button type="button" class="btn btn-outline-dark btn-sm d-print-none" data-print><i class="bi bi-printer"></i> Print</button>
  </div>

  <?php if ($plans): ?>
    <h2 class="h5 mb-1">Monthly protection</h2>
    <p class="small text-body-secondary mb-3">Pay monthly. Every plan includes 24/7 armed response and a priority panic button with a WhatsApp call-back.</p>
    <div class="row g-3 mb-4">
      <?php foreach ($plans as $type => $plan): ?>
        <div class="col-md-4"><?= App\Core\View::partial('shared/plan_card', ['plan' => $plan, 'cta' => [
            $public ? '/register' : '/account/assets/' . App\Services\Plans::DEFAULTS[$type]['kind'] . '/new' . ($type === 'business' ? '?type=business' : ''),
            $public ? 'Join and get protected' : ($type === 'vehicle' ? 'Protect my vehicle' : ($type === 'business' ? 'Protect my business' : 'Protect my home')),
        ]]) ?></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($services): ?>
    <h2 class="h5 mb-1">When you need us</h2>
    <p class="small text-body-secondary mb-3">Once-off services with our people: guards, events and escorts. Send a request and we confirm availability and the price before anything is booked.</p>
    <div class="table-card mb-3"><div class="table-responsive"><table class="table mb-0 align-middle">
      <tbody>
        <?php foreach ($services as $s): $href = ($public ? '/services/' : '/account/services/') . (int) $s['id']; ?>
          <tr>
            <td><a class="fw-semibold" href="<?= $href ?>"><?= e($s['name']) ?></a><div class="small text-body-secondary"><?= e(mb_strimwidth($s['description'], 0, 120, '…')) ?></div></td>
            <td class="text-end text-nowrap"><?= App\Core\View::partial('shared/price', ['s' => $s]) ?><?= $s['pricing'] === 'quote' && $s['price_unit'] ? '<div class="small text-body-secondary">' . e($s['price_unit']) . '</div>' : '' ?></td>
            <td class="text-end d-print-none" style="width:1%"><a class="btn btn-sm btn-outline-dark" href="<?= $href ?>">Book</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div></div>
  <?php endif; ?>
  <?php if (!$plans && !$services): ?><div class="panel text-body-secondary">No services are listed yet.</div><?php endif; ?>
</div>
