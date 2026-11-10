<?php
$o = $_SESSION['_old'] ?? [];
$val = fn($k, $fallback) => e($o[$k] ?? $fallback);
?>
<form method="post" action="/admin/branding" enctype="multipart/form-data" novalidate>
  <?= csrf_field() ?>
  <div class="row g-4">
    <div class="col-xl-8">
      <div class="panel mb-4">
        <h2 class="h6 text-uppercase text-body-secondary mb-3">Brand</h2>
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label" for="display_name">Company display name</label><input class="form-control" id="display_name" name="display_name" required maxlength="150" value="<?= $val('display_name', $tenant['display_name']) ?>"></div>
          <div class="col-md-6">
            <label class="form-label" for="logo">Logo <span class="text-body-secondary">(PNG, JPG or WEBP, max 1 MB)</span></label>
            <input class="form-control" type="file" id="logo" name="logo" accept="image/png,image/jpeg,image/webp">
            <?php if ($tenant['logo_path']): ?>
              <div class="d-flex align-items-center gap-2 mt-2"><img src="<?= e($tenant['logo_path']) ?>" alt="Current logo" class="tenant-logo border"><div class="form-check small"><input class="form-check-input" type="checkbox" name="remove_logo" id="remove_logo" value="1"><label class="form-check-label" for="remove_logo">Remove logo</label></div></div>
            <?php endif; ?>
          </div>
          <div class="col-md-6"><label class="form-label" for="primary_color">Primary colour</label>
            <div class="input-group"><input type="color" class="form-control form-control-color color-swatch" value="<?= $val('primary_color', $tenant['primary_color']) ?>" data-color-sync="primary_color" aria-label="Pick primary colour"><input class="form-control" id="primary_color" name="primary_color" required pattern="#[0-9A-Fa-f]{6}" value="<?= $val('primary_color', $tenant['primary_color']) ?>"></div>
          </div>
          <div class="col-md-6"><label class="form-label" for="secondary_color">Secondary colour <span class="text-body-secondary">(navigation & header)</span></label>
            <div class="input-group"><input type="color" class="form-control form-control-color color-swatch" value="<?= $val('secondary_color', $tenant['secondary_color']) ?>" data-color-sync="secondary_color" aria-label="Pick secondary colour"><input class="form-control" id="secondary_color" name="secondary_color" required pattern="#[0-9A-Fa-f]{6}" value="<?= $val('secondary_color', $tenant['secondary_color']) ?>"></div>
          </div>
          <div class="col-12"><label class="form-label" for="welcome_message">Welcome message</label><input class="form-control" id="welcome_message" name="welcome_message" maxlength="255" value="<?= $val('welcome_message', $tenant['welcome_message']) ?>"></div>
          <div class="col-12"><label class="form-label" for="about">Company introduction</label><textarea class="form-control" id="about" name="about" rows="4" maxlength="3000"><?= $val('about', $tenant['about']) ?></textarea></div>
          <div class="col-12"><label class="form-label" for="services_offered">Services offered <span class="text-body-secondary">(one per line)</span></label><textarea class="form-control" id="services_offered" name="services_offered" rows="5" maxlength="3000"><?= $val('services_offered', $tenant['services_offered']) ?></textarea></div>
        </div>
      </div>
      <div class="panel mb-4">
        <h2 class="h6 text-uppercase text-body-secondary mb-3">Contact & emergency</h2>
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label" for="contact_phone">Office phone</label><input class="form-control" id="contact_phone" name="contact_phone" maxlength="30" value="<?= $val('contact_phone', $tenant['contact_phone']) ?>"></div>
          <div class="col-md-6"><label class="form-label" for="contact_email">Public email</label><input class="form-control" type="email" id="contact_email" name="contact_email" maxlength="190" value="<?= $val('contact_email', $tenant['public_email']) ?>"></div>
          <div class="col-12"><label class="form-label" for="physical_address">Office address</label><input class="form-control" id="physical_address" name="physical_address" maxlength="255" value="<?= $val('physical_address', $tenant['physical_address']) ?>"></div>
          <div class="col-md-6"><label class="form-label" for="emergency_phone">24/7 emergency number</label><input class="form-control" id="emergency_phone" name="emergency_phone" maxlength="30" value="<?= $val('emergency_phone', $tenant['emergency_phone']) ?>"></div>
          <div class="col-md-6"><label class="form-label" for="emergency_notes">Emergency note</label><input class="form-control" id="emergency_notes" name="emergency_notes" maxlength="500" value="<?= $val('emergency_notes', $tenant['emergency_notes']) ?>"></div>
        </div>
      </div>
      <div class="panel mb-4">
        <h2 class="h6 text-uppercase text-body-secondary mb-1">How customers pay</h2>
        <p class="small text-body-secondary">Shown on accepted quotations, e.g. your bank name, account number, branch code, and "use the quotation number as reference".</p>
        <textarea class="form-control" id="payment_instructions" name="payment_instructions" rows="4" aria-label="Payment instructions"><?= $val('payment_instructions', $tenant['payment_instructions'] ?? '') ?></textarea>
      </div>
      <div class="panel mb-4">
        <h2 class="h6 text-uppercase text-body-secondary mb-1">Policies</h2>
        <p class="small text-body-secondary">Leave blank to show the platform's default POPIA-aligned summary under your company name. Have your own policies reviewed by a legal practitioner.</p>
        <div class="mb-3"><label class="form-label" for="privacy_text">Privacy policy</label><textarea class="form-control" id="privacy_text" name="privacy_text" rows="6"><?= $val('privacy_text', $tenant['privacy_text']) ?></textarea></div>
        <div><label class="form-label" for="terms_text">Terms and conditions</label><textarea class="form-control" id="terms_text" name="terms_text" rows="6"><?= $val('terms_text', $tenant['terms_text']) ?></textarea></div>
      </div>
      <button class="btn btn-brand px-4">Save branding</button>
    </div>
    <div class="col-xl-4">
      <div class="panel position-sticky" style="top:80px">
        <h2 class="h6 text-uppercase text-body-secondary mb-3">Your web address</h2>
        <a href="<?= e(app_url('/')) ?>" target="_blank" rel="noopener" class="d-block text-break fw-semibold mb-3"><?= e(app_url('/')) ?></a>
        <p class="small text-body-secondary mb-0">The web address is set when the app is installed (APP_URL), for example app.yourcompany.co.za.</p>
      </div>
    </div>
  </div>
</form>
