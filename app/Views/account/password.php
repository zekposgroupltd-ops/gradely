<?php use App\Core\Auth; $u = Auth::user(); ?>
<div class="grid grid-2">
  <section class="card">
    <div class="card-head"><h2>My account</h2></div>
    <dl class="kv kv-wide"><dt>Name</dt><dd><?= e($u['name']) ?></dd><dt>Email</dt><dd><?= e($u['email']) ?></dd><dt>Role</dt><dd><?= e(role_label($u['role'])) ?></dd><?php if (!empty($u['school_name'])): ?><dt>School</dt><dd><?= e($u['school_name']) ?></dd><?php endif; ?></dl>
  </section>
  <section class="card">
    <div class="card-head"><h2>Change password</h2></div>
    <form class="form compact" method="post" action="<?= e(url('/account/password')) ?>" autocomplete="off">
      <?= csrf_field() ?>
      <?= field_html('Current password', 'current_password', '', 'password', ['required' => true, 'autocomplete' => 'current-password']) ?>
      <?= field_html('New password', 'new_password', '', 'password', ['required' => true, 'autocomplete' => 'new-password', 'minlength' => '10'], '(at least 10 characters)') ?>
      <?= field_html('Confirm new password', 'confirm_password', '', 'password', ['required' => true, 'autocomplete' => 'new-password', 'minlength' => '10']) ?>
      <button class="btn btn-primary" type="submit">Change password</button>
    </form>
  </section>
</div>
