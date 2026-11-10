<?php
$t = tenant();
$u = App\Core\Auth::user();
$tel = preg_replace('/[^\d+]/', '', (string) $t['emergency_phone']);
$alerts = App\Core\DB::all('SELECT * FROM safety_alerts WHERE tenant_id = ? AND (expires_at IS NULL OR expires_at > NOW()) ORDER BY id DESC LIMIT 2', [App\Core\TenantContext::id()]);
$home = $u ? App\Core\Auth::PORTALS[$u['portal']][2] : null;
?>
<section class="hero-community">
  <div class="container">
    <div class="hero-inner">
      <span class="hero-kicker"><i class="bi bi-geo-alt-fill"></i> <?= e(area()['name']) ?> community · <?= e($t['display_name']) ?></span>
      <h1>Safer streets start<br>with neighbours.</h1>
      <p class="lead"><?= e($t['welcome_message'] ?: 'Report what you see, look out for each other, and reach us when it matters.') ?></p>
      <div class="d-flex flex-wrap gap-2 mt-4">
        <?php if ($u): ?>
          <a href="<?= e($home) ?>" class="btn btn-light btn-lg px-4">Open my account</a>
        <?php else: ?>
          <a href="/register" class="btn btn-light btn-lg px-4">Join your community</a>
          <a href="/login" class="btn btn-outline-light btn-lg px-4">Sign in</a>
        <?php endif; ?>
      </div>
      <?php if ($tel): ?>
        <a class="hero-emergency" href="tel:<?= e($tel) ?>"><i class="bi bi-telephone-fill"></i> 24/7 emergency <strong><?= e($t['emergency_phone']) ?></strong></a>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php if ($alerts): ?>
  <div class="container mt-4">
    <?php foreach ($alerts as $al): ?>
      <div class="community-alert level-<?= e($al['level']) ?>">
        <i class="bi bi-megaphone-fill"></i>
        <div><strong><?= e($al['title']) ?></strong><?= $al['area'] ? ' · ' . e($al['area']) : '' ?><div class="small"><?= e($al['body']) ?></div></div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<section class="py-5">
  <div class="container">
    <div class="row g-4 text-center pillars">
      <div class="col-md-4">
        <div class="pillar-ico"><i class="bi bi-eye"></i></div>
        <h2 class="h5">See something, say something</h2>
        <p>Report crime, suspicious activity and accidents in a minute, with a photo and your location.</p>
      </div>
      <div class="col-md-4">
        <div class="pillar-ico"><i class="bi bi-people"></i></div>
        <h2 class="h5">Look out for each other</h2>
        <p>Every report helps your neighbours. Safety alerts keep the whole street informed.</p>
      </div>
      <div class="col-md-4">
        <div class="pillar-ico pillar-ico-red"><i class="bi bi-broadcast-pin"></i></div>
        <h2 class="h5">Help when it matters</h2>
        <p>Complete your profile to unlock the panic button and reach our control room directly.</p>
      </div>
    </div>
  </div>
</section>

<section class="pb-5">
  <div class="container">
    <div class="steps-simple">
      <div><span>1</span>Join for free</div>
      <i class="bi bi-arrow-right"></i>
      <div><span>2</span>Complete your profile</div>
      <i class="bi bi-arrow-right"></i>
      <div><span>3</span>You're connected</div>
    </div>
    <p class="text-center text-body-secondary small mt-3 mb-0">Free for everyone: the panic button, incident reports and a WhatsApp call-back from our control room.</p>
  </div>
</section>

<?php $plans = App\Services\Plans::all(); if ($plans): ?>
<section class="pb-5">
  <div class="container">
    <div class="text-center mb-4">
      <h2 class="h4 mb-1">Protection plans</h2>
      <p class="text-body-secondary mb-0">One monthly plan for what matters most. Every plan includes 24/7 armed response.</p>
    </div>
    <div class="row g-3 justify-content-center">
      <?php foreach ($plans as $type => $plan): ?>
        <div class="col-md-4"><?= App\Core\View::partial('shared/plan_card', ['plan' => $plan, 'cta' => [
            ($u && $u['portal'] === 'homeowner') ? '/account/assets/' . App\Services\Plans::DEFAULTS[$type]['kind'] . '/new' . ($type === 'business' ? '?type=business' : '') : '/register',
            $type === 'vehicle' ? 'Protect my vehicle' : ($type === 'business' ? 'Protect my business' : 'Protect my home'),
        ]]) ?></div>
      <?php endforeach; ?>
    </div>
    <p class="text-center text-body-secondary small mt-4 mb-0">
      <a href="/pricelist">Full price list</a> · <a href="/services">Once-off services</a><br>
      In a life-threatening emergency, also call SAPS on <a href="tel:10111">10111</a> or an ambulance on <a href="tel:10177">10177</a>.
    </p>
  </div>
</section>
<?php endif; ?>
