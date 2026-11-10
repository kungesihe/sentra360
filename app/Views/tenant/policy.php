<?php
$t = tenant();
$text = $kind === 'privacy' ? $t['privacy_text'] : $t['terms_text'];
?>
<div class="container py-5 prose">
  <h1 class="h2 mb-4"><?= e($title) ?></h1>
  <?php if ($text): ?>
    <div style="white-space:pre-line"><?= e($text) ?></div>
  <?php elseif ($kind === 'privacy'): ?>
    <div class="alert alert-light border small"><?= e($t['display_name']) ?> has not yet published its own privacy policy. The summary below explains how your information is handled on this platform.</div>
    <p><strong><?= e($t['display_name']) ?></strong> is the responsible party for the personal information you provide here, under the Protection of Personal Information Act (POPIA). The platform is operated on its behalf by <?= e(config('name')) ?>.</p>
    <h2>What we collect</h2>
    <p>Your name, surname, mobile number, email address and, when you use our services, information such as incident reports, property and vehicle details and location.</p>
    <h2>Why</h2>
    <p>To verify your account, respond to reports and requests, provide security services you apply for, and keep the community informed. We will only send marketing messages if you agreed to receive them.</p>
    <h2>Who can see it</h2>
    <p>Only authorised staff of <?= e($t['display_name']) ?>, and only what they need for their duties. Access to sensitive details is logged. Your information is never shared with other security companies on the platform.</p>
    <h2>Your rights</h2>
    <p>You may request access to, correction of or deletion of your information and withdraw marketing consent at any time<?= $t['public_email'] ? ' by emailing ' . e($t['public_email']) : '' ?>. You may also complain to the Information Regulator at inforegulator.org.za.</p>
  <?php else: ?>
    <div class="alert alert-light border small"><?= e($t['display_name']) ?> has not yet published its own terms. The general terms below apply.</div>
    <p>This community platform is provided by <strong><?= e($t['display_name']) ?></strong>. Registering does not make you a client or create a security service agreement. Services, prices and response commitments apply only once you accept a quotation from <?= e($t['display_name']) ?>.</p>
    <p>Submitting an incident report does not mean <?= e($t['display_name']) ?> has accepted responsibility to respond. In a life-threatening emergency, also call SAPS on 10111 or an ambulance on 10177.</p>
    <p>You agree to provide accurate information and not to submit false reports.</p>
  <?php endif; ?>
</div>
