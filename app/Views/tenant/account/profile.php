<div class="container py-4" style="max-width:1000px">
  <?= App\Core\View::partial('home/_nav', ['active' => '/account/profile']) ?>
  <h1 class="h4 mb-4">Profile & security</h1>
  <?php require BASE_PATH . '/app/Views/partials/flash.php'; ?>
  <div class="row g-4">
    <div class="col-lg-6">
      <form method="post" action="/account/profile" class="panel h-100">
        <?= csrf_field() ?>
        <h2 class="h5 mb-3">Personal details</h2>
        <div class="mb-3"><label class="form-label" for="first_name">Full name</label><input class="form-control" id="first_name" name="first_name" required maxlength="100" value="<?= e($user['first_name']) ?>"></div>
        <div class="mb-3"><label class="form-label" for="surname">Surname</label><input class="form-control" id="surname" name="surname" required maxlength="100" value="<?= e($user['surname']) ?>"></div>
        <div class="mb-3"><label class="form-label">Mobile</label><input class="form-control" value="<?= e($user['mobile']) ?>" disabled></div>
        <div class="mb-3"><label class="form-label">Email</label><input class="form-control" value="<?= e($user['email']) ?>" disabled></div>
        <button class="btn btn-brand">Save</button>
      </form>
    </div>
    <div class="col-lg-6">
      <form method="post" action="/account/password" class="panel h-100">
        <?= csrf_field() ?>
        <h2 class="h5 mb-3">Change password</h2>
        <div class="mb-3"><label class="form-label" for="current_password">Current password</label><input class="form-control" type="password" id="current_password" name="current_password" required autocomplete="current-password"></div>
        <div class="mb-3"><label class="form-label" for="password">New password</label><input class="form-control" type="password" id="password" name="password" required minlength="10" autocomplete="new-password"></div>
        <div class="mb-3"><label class="form-label" for="password_confirm">Confirm new password</label><input class="form-control" type="password" id="password_confirm" name="password_confirm" required autocomplete="new-password"></div>
        <button class="btn btn-brand">Change password</button>
      </form>
    </div>
    <div class="col-12">
      <div class="panel">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
          <h2 class="h5 mb-0">Signed-in devices</h2>
          <form method="post" action="/account/sessions/revoke" data-confirm="Sign out of all devices, including this one?"><?= csrf_field() ?><button class="btn btn-outline-danger btn-sm">Sign out everywhere</button></form>
        </div>
        <div class="table-responsive">
          <table class="table table-sm small mb-0">
            <thead><tr><th>Device</th><th>IP address</th><th>Signed in</th><th>Last active</th></tr></thead>
            <tbody>
            <?php foreach ($sessions as $s): ?>
              <tr><td class="text-truncate" style="max-width:280px"><?= e($s['user_agent'] ?: 'Unknown') ?></td><td><?= e($s['ip_address']) ?></td><td><?= e($s['created_at']) ?></td><td><?= e($s['last_seen_at']) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <div class="col-12">
      <div class="panel">
        <h2 class="h5 mb-3">Your privacy</h2>
        <ul class="small mb-2">
          <?php foreach ($consents as $c): ?>
            <li><?= e(str_replace('_', ' & ', $c['consent_type'])) ?> — <?= $c['granted'] ? 'given' : 'withdrawn' ?> on <?= e(date('j M Y', strtotime($c['created_at']))) ?> (version <?= e($c['document_version']) ?>)</li>
          <?php endforeach; ?>
        </ul>
        <p class="small mb-2"><a href="/delete-account" class="text-danger"><i class="bi bi-trash"></i> Delete my account</a></p>
        <p class="small text-body-secondary mb-0">To request a copy of your information or a correction, contact <?= e(tenant()['display_name']) ?><?= tenant()['public_email'] ? ' at ' . e(tenant()['public_email']) : '' ?>. See the <a href="/privacy">privacy policy</a>.</p>
      </div>
    </div>
  </div>
</div>
