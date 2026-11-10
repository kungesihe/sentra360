<?php /** Service price label. Needs $s. */
echo match ($s['pricing']) {
    'fixed' => '<strong>' . money_exact((int) $s['price_cents']) . '</strong>' . ($s['price_unit'] ? ' <span class="text-body-secondary">' . e($s['price_unit']) . '</span>' : ''),
    'from'  => 'From <strong>' . money_exact((int) $s['price_cents']) . '</strong>' . ($s['price_unit'] ? ' <span class="text-body-secondary">' . e($s['price_unit']) . '</span>' : ''),
    default => '<span class="text-body-secondary">Quotation required</span>',
};
