<div class="container">
  <div class="auth-card wide">
    <h1 class="h3 mb-1">Create your homeowner account</h1>
    <p class="text-body-secondary mb-4">Free to join. You'll verify your mobile number and email after signing up.</p>
    <?php require BASE_PATH . '/app/Views/partials/flash.php'; ?>
    <form method="post" action="/register" class="panel" novalidate>
      <?= csrf_field() ?>
      <div class="row g-3">
        <div class="col-md-6"><label class="form-label" for="first_name">Full name</label><input class="form-control" id="first_name" name="first_name" required maxlength="100" autocomplete="given-name" value="<?= old('first_name') ?>"></div>
        <div class="col-md-6"><label class="form-label" for="surname">Surname</label><input class="form-control" id="surname" name="surname" required maxlength="100" autocomplete="family-name" value="<?= old('surname') ?>"></div>
        <div class="col-md-6"><label class="form-label" for="mobile">Mobile number</label><input class="form-control" type="tel" id="mobile" name="mobile" required placeholder="082 123 4567" autocomplete="tel" value="<?= old('mobile') ?>"></div>
        <div class="col-md-6"><label class="form-label" for="email">Email address</label><input class="form-control" type="email" id="email" name="email" required autocomplete="email" value="<?= old('email') ?>"></div>
        <div class="col-md-6"><label class="form-label" for="password">Password</label>
          <div class="input-group"><input class="form-control" type="password" id="password" name="password" required minlength="10" autocomplete="new-password"><button class="btn btn-outline-secondary" type="button" data-toggle-password="password" aria-label="Show password"><i class="bi bi-eye"></i></button></div>
          <div class="form-text">At least 10 characters, with letters and a number.</div>
        </div>
        <div class="col-md-6"><label class="form-label" for="password_confirm">Confirm password</label><input class="form-control" type="password" id="password_confirm" name="password_confirm" required autocomplete="new-password"></div>
      </div>
      <div class="form-check mt-4">
        <input class="form-check-input" type="checkbox" id="consent" name="consent" value="1" required>
        <label class="form-check-label small" for="consent">I agree to the <a href="/terms" target="_blank">terms</a> and the <a href="/privacy" target="_blank">privacy policy</a>, and consent to <?= e(tenant()['display_name']) ?> processing my information to provide this service.</label>
      </div>
      <div class="form-check mt-2">
        <input class="form-check-input" type="checkbox" id="marketing" name="marketing" value="1">
        <label class="form-check-label small" for="marketing">Send me safety alerts and news about services. <span class="text-body-secondary">(optional, you can stop at any time)</span></label>
      </div>
      <button class="btn btn-brand w-100 mt-4 py-2">Create account</button>
      <p class="text-center small mt-3 mb-0">Already registered? <a href="/login">Sign in</a></p>
    </form>
  </div>
</div>
