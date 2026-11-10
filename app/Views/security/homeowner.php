<a href="/security/homeowners" class="small"><i class="bi bi-arrow-left"></i> Homeowners</a>
<div class="row g-4 mt-1">
  <div class="col-lg-8"><?= App\Core\View::partial('shared/homeowner_details', ['m' => $m]) ?></div>
  <div class="col-lg-4"><div class="panel small text-body-secondary">Security staff can view homeowner records. Changes such as verifying or suspending an account are made by the Admin.</div></div>
</div>
