<div class="center-card text-center">
  <div class="panel">
    <p>Taking you to PayFast's secure payment page…</p>
    <form method="post" action="<?= e($action) ?>" data-autosubmit>
      <?php foreach ($fields as $k => $v): ?><input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>"><?php endforeach; ?>
      <button class="btn btn-dark">Continue to PayFast</button>
    </form>
  </div>
</div>
