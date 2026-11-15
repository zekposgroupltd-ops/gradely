<?php use App\Core\Auth; /** @var array $admins */ ?>
<div class="tabs" role="navigation" aria-label="Platform"><a class="tab is-active" href="<?= e(url('/super/admins')) ?>">Administrators</a><a class="tab" href="<?= e(url('/super/notifications')) ?>">Notifications</a><a class="tab" href="<?= e(url('/super/settings')) ?>">Settings</a></div>
<div class="grid grid-2">
  <section class="card">
    <div class="card-head"><h2>Platform administrators</h2></div>
    <div class="table-wrap"><table class="table table-tight">
      <thead><tr><th>Name</th><th>Status</th><th></th></tr></thead>
      <tbody><?php foreach ($admins as $a): ?>
        <tr><td><strong><?= e($a['name']) ?></strong><br><span class="muted small"><?= e($a['email']) ?> · last sign-in <?= e($a['last_login_at'] ?? 'never') ?></span></td><td><?= status_badge($a['status']) ?></td>
          <td class="table-actions"><?php if ((int) $a['id'] !== (int) Auth::user()['id']): ?>
            <form class="inline-form" method="post" action="<?= e(url('/super/admins/' . $a['id'] . '/toggle')) ?>" data-confirm="Change the status of <?= e($a['email']) ?>?"><?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit"><?= $a['status'] === 'active' ? 'Disable' : 'Enable' ?></button></form>
          <?php else: ?><span class="muted small">You</span><?php endif; ?></td></tr>
      <?php endforeach; ?></tbody>
    </table></div>
  </section>
  <section class="card">
    <div class="card-head"><h2>Add an administrator</h2></div>
    <form class="form compact" method="post" action="<?= e(url('/super/admins')) ?>" autocomplete="off">
      <?= csrf_field() ?>
      <?= field_html('Full name', 'name', old('name'), 'text', ['required' => true, 'maxlength' => '150']) ?>
      <?= field_html('Email', 'email', old('email'), 'email', ['required' => true]) ?>
      <?= field_html('Password', 'password', '', 'password', ['autocomplete' => 'new-password', 'minlength' => '10'], '(leave blank to generate one)') ?>
      <button class="btn btn-primary" type="submit">Create administrator</button>
    </form>
  </section>
</div>
