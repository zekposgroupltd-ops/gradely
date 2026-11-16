<?php /** @var array $schools */ ?>
<div class="tabs" role="navigation" aria-label="Platform"><a class="tab" href="<?= e(url('/super/admins')) ?>">Administrators</a><a class="tab is-active" href="<?= e(url('/super/notifications')) ?>">Notifications</a><a class="tab" href="<?= e(url('/super/settings')) ?>">Settings</a></div>
<section class="card form-card">
  <div class="card-head"><h2>Send a notification to school administrators</h2></div>
  <form class="form compact" method="post" action="<?= e(url('/super/notifications')) ?>">
    <?= csrf_field() ?>
    <?= select_html('Send to', 'school_id', array_column($schools, 'name', 'id'), '', [], 'Every active school') ?>
    <?= field_html('Title', 'title', '', 'text', ['required' => true, 'maxlength' => '150']) ?>
    <label class="field"><span class="field-label">Message</span><textarea name="body" maxlength="500" rows="4"></textarea></label>
    <button class="btn btn-primary" type="submit">Send notification</button>
  </form>
</section>
