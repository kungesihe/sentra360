<?php $t = tenant(); $u = App\Core\Auth::user(); ?>
<section class="tenant-hero">
  <div class="container">
    <div class="row align-items-center g-4">
      <div class="col-lg-7">
        <h1 class="mb-3"><?= e($t['welcome_message'] ?: 'Welcome to ' . $t['display_name']) ?></h1>
        <?php if ($t['about']): ?><p class="lead mb-4"><?= e(mb_strimwidth($t['about'], 0, 260, '…')) ?></p><?php endif; ?>
        <div class="d-flex flex-wrap gap-2">
          <?php if ($u): ?>
            <a href="/account" class="btn btn-brand-light btn-lg px-4">Go to my account</a>
          <?php else: ?>
            <a href="/register" class="btn btn-brand-light btn-lg px-4">Join as a homeowner</a>
            <a href="/login" class="btn btn-outline-light btn-lg px-4">Sign in</a>
          <?php endif; ?>
        </div>
      </div>
      <?php if ($t['emergency_phone']): ?>
      <div class="col-lg-5">
        <div class="emergency-box text-body">
          <div class="d-flex align-items-center gap-2 mb-2"><i class="bi bi-telephone-fill text-danger fs-4"></i><span class="fw-bold">24/7 emergency line</span></div>
          <a href="tel:<?= e(preg_replace('/[^\d+]/', '', $t['emergency_phone'])) ?>" class="fs-3 fw-bold text-danger text-decoration-none d-block"><?= e($t['emergency_phone']) ?></a>
          <?php if ($t['emergency_notes']): ?><p class="small mb-0 mt-2"><?= e($t['emergency_notes']) ?></p><?php endif; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<section class="py-5">
  <div class="container">
    <h2 class="h3 mb-4">How can we help?</h2>
    <div class="row g-3">
      <?php
      $actions = [
        ['incident_reporting', 'megaphone', 'Report an incident', 'Crime, suspicious activity, accidents and emergencies in your area.'],
        ['residential_applications', 'house-check', 'Protect your home', 'Apply for armed response, monitoring, patrols, CCTV or fencing.'],
        ['vehicle_applications', 'car-front', 'Protect your vehicle', 'Apply for vehicle protection services.'],
        ['service_marketplace', 'bag-check', 'Specialised services', 'VIP escorts, event security, assessments and more.'],
      ];
      foreach ($actions as [$key, $ico, $h, $p]):
        ?>
        <div class="col-sm-6 col-lg-3">
          <div class="action-card disabled" aria-disabled="true">
            <div class="ico"><i class="bi bi-<?= $ico ?>"></i></div>
            <div class="fw-semibold"><?= $h ?></div>
            <p class="small text-body-secondary mb-2"><?= $p ?></p>
            <span class="badge text-bg-light border">Opening soon</span>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <p class="small text-body-secondary mt-3 mb-0">Register now so you're ready when online reporting and applications open.</p>
  </div>
</section>

<?php if ($services): ?>
<section class="py-5 bg-body-tertiary">
  <div class="container">
    <h2 class="h3 mb-4">Our services</h2>
    <div class="row g-3">
      <?php foreach ($services as $s): ?>
        <div class="col-sm-6 col-lg-4"><div class="d-flex align-items-center gap-2 p-3 bg-white border rounded-3"><i class="bi bi-shield-check" style="color:var(--brand)"></i><span><?= e($s) ?></span></div></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="py-5">
  <div class="container">
    <div class="row g-4">
      <?php if ($t['about']): ?>
      <div class="col-lg-7">
        <h2 class="h3 mb-3">About <?= e($t['display_name']) ?></h2>
        <p class="text-body-secondary" style="white-space:pre-line"><?= e($t['about']) ?></p>
      </div>
      <?php endif; ?>
      <div class="col-lg-5">
        <div class="panel">
          <h3 class="h6 text-uppercase text-body-secondary">Contact us</h3>
          <?php if ($t['contact_phone']): ?><div class="mb-1"><i class="bi bi-telephone me-2"></i><?= e($t['contact_phone']) ?></div><?php endif; ?>
          <?php if ($t['public_email']): ?><div class="mb-1"><i class="bi bi-envelope me-2"></i><?= e($t['public_email']) ?></div><?php endif; ?>
          <?php if ($t['physical_address']): ?><div><i class="bi bi-geo-alt me-2"></i><?= e($t['physical_address']) ?></div><?php endif; ?>
        </div>
        <div class="alert alert-warning small mt-3 mb-0"><i class="bi bi-exclamation-triangle"></i> In a life-threatening emergency, also call SAPS on <strong>10111</strong> or an ambulance on <strong>10177</strong>.</div>
      </div>
    </div>
  </div>
</section>
