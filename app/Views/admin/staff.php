<div class="row g-4">
  <div class="col-xl-7">
    <div class="table-card">
      <div class="table-responsive">
        <table class="table">
          <thead><tr><th>Name</th><th>Login type</th><th>Email</th><th>Status</th><th>Last sign-in</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($staff as $s): $self = (int) $s['id'] === (int) $me['id']; ?>
            <tr>
              <td class="fw-semibold"><?= e($s['first_name'] . ' ' . $s['surname']) ?><?= $self ? ' <span class="badge text-bg-light border">you</span>' : '' ?></td>
              <td><i class="bi bi-<?= $s['role_code'] === 'admin' ? 'building' : 'shield-shaded' ?>"></i> <?= e($s['role_name']) ?></td>
              <td class="text-break"><?= e($s['email']) ?></td>
              <td><?= status_badge($s['status']) ?></td>
              <td class="small text-body-secondary"><?= e($s['last_login_at'] ?? 'Never') ?></td>
              <td class="text-end">
                <?php if (!$self): ?>
                  <form method="post" action="/admin/staff/<?= (int) $s['id'] ?>/status"<?= $s['status'] !== 'disabled' ? ' data-confirm="Disable this login? They will be signed out immediately."' : '' ?>>
                    <?= csrf_field() ?><input type="hidden" name="action" value="<?= $s['status'] === 'disabled' ? 'enable' : 'disable' ?>">
                    <button class="btn btn-sm <?= $s['status'] === 'disabled' ? 'btn-outline-primary' : 'btn-outline-danger' ?>"><?= $s['status'] === 'disabled' ? 'Enable' : 'Disable' ?></button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-xl-5">
    <form method="post" action="/admin/staff" class="panel" novalidate>
      <?= csrf_field() ?>
      <h2 class="h5 mb-3">Create a login</h2>
      <div class="mb-3">
        <span class="form-label d-block">Login type</span>
        <?php $sel = $_SESSION['_old']['role'] ?? 'security_staff'; ?>
        <div class="form-check"><input class="form-check-input" type="radio" name="role" id="r-sec" value="security_staff"<?= $sel === 'security_staff' ? ' checked' : '' ?>><label class="form-check-label" for="r-sec"><strong>Security company</strong>: control room or response officer. Lands in the control room after signing in.</label></div>
        <div class="form-check"><input class="form-check-input" type="radio" name="role" id="r-adm" value="admin"<?= $sel === 'admin' ? ' checked' : '' ?>><label class="form-check-label" for="r-adm"><strong>Admin</strong>: office staff with full control. Lands in the admin portal after signing in.</label></div>
      </div>
      <div class="row g-3">
        <div class="col-6"><label class="form-label" for="first_name">First name</label><input class="form-control" id="first_name" name="first_name" required value="<?= old('first_name') ?>"></div>
        <div class="col-6"><label class="form-label" for="surname">Surname</label><input class="form-control" id="surname" name="surname" required value="<?= old('surname') ?>"></div>
        <div class="col-12"><label class="form-label" for="email">Email</label><input class="form-control" type="email" id="email" name="email" required value="<?= old('email') ?>"></div>
        <div class="col-12"><label class="form-label" for="mobile">Mobile</label><input class="form-control" type="tel" id="mobile" name="mobile" required value="<?= old('mobile') ?>"></div>
        <div class="col-12"><label class="form-label" for="password">Temporary password</label><input class="form-control" type="text" id="password" name="password" required minlength="10" autocomplete="off"><div class="form-text">At least 10 characters with letters and a number. Ask them to change it under "My login".</div></div>
      </div>
      <button class="btn btn-brand mt-3">Create login</button>
    </form>
  </div>
</div>
