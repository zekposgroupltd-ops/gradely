<?php /** @var array $cfg */ ?>
<div class="tabs" role="navigation" aria-label="Platform"><a class="tab" href="<?= e(url('/super/admins')) ?>">Administrators</a><a class="tab" href="<?= e(url('/super/notifications')) ?>">Notifications</a><a class="tab" href="<?= e(url('/super/pin-requests')) ?>">PIN requests</a><a class="tab is-active" href="<?= e(url('/super/settings')) ?>">Settings</a></div>
<section class="card form-card">
  <div class="card-head"><h2>Platform settings</h2></div>
  <form class="form compact" method="post" action="<?= e(url('/super/settings')) ?>">
    <?= csrf_field() ?>
    <div class="form-row">
      <?= field_html('Support email', 'support_email', $cfg['support_email'], 'email', ['maxlength' => '190'], '(shown on the result portal)') ?>
      <?= field_html('Support phone', 'support_phone', $cfg['support_phone'], 'tel', ['maxlength' => '40']) ?>
    </div>
    <?= field_html('Result-checking PIN price per student (₦)', 'pin_price_per_student', $cfg['pin_price_per_student'] ?? '0.00', 'number', ['min' => '0', 'step' => '0.01', 'required' => true], '(charged per student; school requests are calculated from class or school enrolment)') ?>
    <label class="field"><span class="field-label">Announcement banner <span class="hint">(shown on every school dashboard; leave blank for none)</span></span><textarea name="announcement" maxlength="400" rows="3"><?= e($cfg['announcement']) ?></textarea></label>
    <button class="btn btn-primary" type="submit">Save settings</button>
  </form>
</section>
